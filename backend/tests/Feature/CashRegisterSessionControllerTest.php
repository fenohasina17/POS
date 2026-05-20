<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\User;
use App\Models\PointOfSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CashRegisterSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Nettoyage du cache des permissions pour éviter les conflits entre tests
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Méthode helper pour créer un environnement de test complet.
     */
    private function authenticate(string $permission = null)
    {
        $pos = PointOfSale::factory()->create();
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'point_of_sale_id' => $pos->id,
        ]);

        if ($permission) {
            Permission::findOrCreate($permission, 'api');
            $user->givePermissionTo($permission);
        }

        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('token') ?? $loginResponse->json('access_token');

        return [$user, $token, $pos];
    }

    #[Test]
    public function un_utilisateur_peut_lister_ses_sessions()
    {
        [$user, $token, $pos] = $this->authenticate('view.cash_register_sessions');
        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);

        CashRegisterSession::factory()->count(3)->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => false
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/cash-register-sessions')
            ->assertStatus(200)
            ->assertJsonCount(3);
    }

    #[Test]
    public function un_utilisateur_peut_voir_une_session_specifique()
    {
        [$user, $token, $pos] = $this->authenticate('view.cash_register_sessions');
        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/cash-register-sessions/{$session->id}")
            ->assertStatus(200)
            ->assertJsonPath('id', $session->id);
    }

    #[Test]
    public function un_utilisateur_autorise_peut_ajouter_un_ecart_a_une_session()
    {
        [$user, $token, $pos] = $this->authenticate('update.cash_register_sessions');
        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);
        $session = CashRegisterSession::factory()->create([
            'cash_register_id' => $register->id,
            'user_id' => $user->id
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/cash-register-sessions/{$session->id}/discrepancies", [
                'description' => 'Erreur de caisse -5€',
                'amount' => -5.00
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('session_discrepancies', [
            'session_id' => $session->id,
            'difference_amount' => -5.00,
            'explanation' => 'Erreur de caisse -5€'
        ]);
    }

    #[Test]
    public function un_utilisateur_autorise_peut_cloturer_sa_session()
    {
        [$user, $token, $pos] = $this->authenticate('update.cash_register_sessions');
        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => false,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/cash-register-sessions/{$session->id}", [
                'is_closed' => true,
                'actual_cash_amount' => 150.50,
                'closed_at' => now()->toDateTimeString(),
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('cash_register_sessions', [
            'id' => $session->id,
            'is_closed' => true,
            'actual_cash_amount' => 150.50,
        ]);
    }

    #[Test]
    public function la_cloture_echoue_si_le_montant_reel_est_manquant()
    {
        [$user, $token, $pos] = $this->authenticate('update.cash_register_sessions');
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'is_closed' => false
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/cash-register-sessions/{$session->id}", [
                'is_closed' => true,
                'closed_at' => now()->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'To close the session, actual_cash_amount and closed_at are required.');
    }

    #[Test]
    public function un_utilisateur_autorise_peut_reouvrir_une_session_fermee()
    {
        [$user, $token, $pos] = $this->authenticate('update.cash_register_sessions');
        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => true,
            'closed_at' => now()->subHour(),
            'actual_cash_amount' => 200.00
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/cash-register-sessions/{$session->id}/reopen")
            ->assertStatus(200)
            ->assertJsonPath('is_closed', false);
    }

    #[Test]
    public function on_peut_verifier_le_statut_d_une_caisse()
    {
        [$user, $token, $pos] = $this->authenticate();
        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);

        // Vérification statut Disponible
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/cash-register-sessions/status/{$register->id}")
            ->assertStatus(200)
            ->assertJsonPath('status', 'available');

        // Création d'une session pour changer le statut
        CashRegisterSession::factory()->create([
            'cash_register_id' => $register->id,
            'user_id' => $user->id,
            'is_closed' => false
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/cash-register-sessions/status/{$register->id}")
            ->assertStatus(200)
            ->assertJsonPath('status', 'in_use');
    }

    #[Test]
    public function un_utilisateur_peut_recuperer_sa_propre_session_active()
    {
        [$user, $token, $pos] = $this->authenticate();
        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => false
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/my-active-session")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $session->id);
    }

    #[Test]
    public function un_utilisateur_autorise_peut_supprimer_une_session()
    {
        [$user, $token, $pos] = $this->authenticate('delete.cash_register_sessions');
        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/cash-register-sessions/{$session->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('cash_register_sessions', ['id' => $session->id]);
    }
    #[Test]
    public function un_utilisateur_autorise_peut_consulter_le_resume_d_une_session_fermee()
    {
        // 1. Setup : Authentification avec la permission de lecture
        [$user, $token, $pos] = $this->authenticate('view.cash_register_sessions');

        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);

        // 2. Créer une session déjà FERMÉE
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => true,
            'closed_at' => now(),
            'actual_cash_amount' => 500.00
        ]);

        // 3. Action : Appel de la route summary
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/cash-register-sessions/{$session->id}/summary");

        // 4. Assertions
        $response->assertStatus(200)
            ->assertJsonStructure([
                'session',
                'categories',
                'payments',
                'total_sales',
            ]);
    }
    #[Test]
    public function un_utilisateur_peut_ouvrir_une_session_de_caisse()
    {
        [$user, $token, $pos] = $this->authenticate('create.cash_register_sessions');
        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/cash-register-sessions', [
                'cash_register_id' => $register->id,
                'user_id' => $user->id,
                'starting_amount' => 100.00,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cash_register_sessions', [
            'cash_register_id' => $register->id,
            'starting_amount' => 100.00,
            'is_closed' => false
        ]);
    }

    #[Test]
    public function on_ne_peut_pas_ouvrir_deux_sessions_sur_la_meme_caisse()
    {
        [$user, $token, $pos] = $this->authenticate('create.cash_register_sessions');
        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);

        // On crée une première session ouverte
        CashRegisterSession::factory()->create([
            'cash_register_id' => $register->id,
            'is_closed' => false
        ]);

        // Tentative d'en ouvrir une deuxième
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/cash-register-sessions', [
                'cash_register_id' => $register->id,
                'user_id' => $user->id,
                'starting_amount' => 50.00,
            ]);

        $response->assertStatus(409); // Conflict
        $response->assertJsonPath('message', 'There is already an open session for this cash register.');
    }

    #[Test]
    public function un_utilisateur_peut_lister_les_ecarts_d_une_session()
    {
        // Attention : ton contrôleur utilise 'view.cash_register_sessions' à la ligne 306
        [$user, $token, $pos] = $this->authenticate('view.cash_register_sessions');
        $session = CashRegisterSession::factory()->create(['user_id' => $user->id]);

        // Créer des écarts
        $session->discrepancies()->create([
            'explanation' => 'Ecart 1',
            'difference_amount' => 10.00
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/cash-register-sessions/{$session->id}/discrepancies");

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    #[Test]
    public function un_gerant_ne_peut_pas_creer_de_session()
    {
        // On crée un gérant
        [$user, $token, $pos] = $this->authenticate('create.cash_register_sessions');

        // On lui donne le rôle gérant (pour déclencher userIsManager)
        $gerantRole = \Spatie\Permission\Models\Role::findOrCreate('gerant', 'api');
        $user->assignRole($gerantRole);
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/cash-register-sessions', [
                'cash_register_id' => $register->id,
                'user_id' => $user->id,
                'starting_amount' => 100.00,
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Les gérants ne peuvent pas créer de session de caisse.');
    }
    #[Test]
    public function un_utilisateur_ne_peut_pas_ouvrir_une_caisse_d_un_autre_point_de_vente()
    {
        [$user, $token, $pos] = $this->authenticate('create.cash_register_sessions');

        // On crée une caisse liée à un AUTRE Point de Vente
        $autrePos = PointOfSale::factory()->create();
        $registerEtranger = CashRegister::factory()->create(['point_of_sale_id' => $autrePos->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/cash-register-sessions', [
                'cash_register_id' => $registerEtranger->id,
                'user_id' => $user->id,
                'starting_amount' => 100.00,
            ]);

        // Doit échouer car la caisse n'appartient pas au POS de l'user
        $response->assertStatus(422);
    }
    #[Test]
    public function on_ne_peut_pas_voir_le_resume_d_une_session_encore_ouverte()
    {
        [$user, $token, $pos] = $this->authenticate('view.cash_register_sessions');

        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);

        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => false // Session ouverte
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/cash-register-sessions/{$session->id}/summary");

        // Le contrôleur retourne 409 Conflict
        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Le résumé ne peut être consulté que pour une session fermée.');
    }
    #[Test]
    public function un_gerant_ne_peut_pas_voir_une_session_d_un_autre_point_de_vente()
    {
        // Créer un gérant au POS A
        [$user, $token, $posA] = $this->authenticate('view.cash_register_sessions');
        $role = \Spatie\Permission\Models\Role::findOrCreate('gerant', 'api');
        $user->assignRole($role);
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Créer une session dans un POS B
        $posB = \App\Models\PointOfSale::factory()->create();
        $registerB = \App\Models\CashRegister::factory()->create(['point_of_sale_id' => $posB->id]);
        $sessionB = \App\Models\CashRegisterSession::factory()->create([
            'cash_register_id' => $registerB->id
        ]);

        // Le gérant A essaie de voir la session du magasin B
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/cash-register-sessions/{$sessionB->id}");

        $response->assertStatus(403);
    }



    #[Test]
    public function on_ne_peut_pas_reouvrir_une_session_deja_ouverte()
    {
        [$user, $token, $pos] = $this->authenticate('update.cash_register_sessions');
        $session = \App\Models\CashRegisterSession::factory()->create(['is_closed' => false]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/cash-register-sessions/{$session->id}/reopen");

        // 400 Bad Request selon ton contrôleur ligne 285
        $response->assertStatus(400);
        $response->assertJsonPath('message', 'Session is already open.');
    }
}