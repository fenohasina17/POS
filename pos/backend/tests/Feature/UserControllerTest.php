<?php

namespace Tests\Feature;

use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $caissier;
    private string $adminToken;
    private string $caissierToken;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view.users', 'create.users', 'update.users', 'delete.users'] as $perm) {
            Permission::findOrCreate($perm, 'api');
        }

        $adminRole    = Role::findOrCreate('admin', 'api');
        $caissierRole = Role::findOrCreate('caissier', 'api');

        $adminRole->givePermissionTo(['view.users', 'create.users', 'update.users', 'delete.users']);

        $pos = PointOfSale::factory()->create();

        $this->admin = User::factory()->create([
            'email'            => 'admin@igp.com',
            'password'         => Hash::make('password123'),
            'point_of_sale_id' => $pos->id,
        ]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->caissier = User::factory()->create([
            'email'            => 'caissier@igp.com',
            'password'         => Hash::make('password123'),
            'point_of_sale_id' => $pos->id,
        ]);
        $this->caissier->assignRole('caissier');
        $this->caissierToken = $this->caissier->createToken('test')->plainTextToken;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function asAdmin(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    private function asCaissier(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->caissierToken);
    }

    // =========================================================================
    // GET /api/users
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_utilisateurs()
    {
        $response = $this->asAdmin()->getJson('/api/users');

        $response->assertStatus(200)
                 ->assertJsonIsArray()
                 ->assertJsonCount(2); // admin + caissier créés dans setUp
    }

    #[Test]
    public function un_non_authentifie_ne_peut_pas_lister_les_utilisateurs()
    {
        $this->getJson('/api/users')->assertStatus(401);
    }

    #[Test]
    public function caissier_ne_peut_pas_lister_les_utilisateurs()
    {
        $this->asCaissier()->getJson('/api/users')->assertStatus(403);
    }

    // =========================================================================
    // GET /api/users/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_voir_un_utilisateur()
    {
        $response = $this->asAdmin()->getJson("/api/users/{$this->caissier->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('email', 'caissier@igp.com');
    }

    #[Test]
    public function retourne_404_pour_un_utilisateur_inexistant()
    {
        $this->asAdmin()->getJson('/api/users/99999')->assertStatus(404);
    }

    #[Test]
    public function caissier_ne_peut_pas_voir_un_utilisateur()
    {
        $this->asCaissier()
             ->getJson("/api/users/{$this->admin->id}")
             ->assertStatus(403);
    }

    // =========================================================================
    // POST /api/users
    // =========================================================================

    #[Test]
    public function admin_peut_creer_un_utilisateur()
    {
        $pos = PointOfSale::factory()->create();

        $response = $this->asAdmin()->postJson('/api/users', [
            'name'             => 'Nouveau',
            'email'            => 'nouveau@igp.com',
            'password'         => 'secret123',
            'point_of_sale_id' => $pos->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'nouveau@igp.com']);
    }

    #[Test]
    public function creation_echoue_si_email_deja_utilise()
    {
        $response = $this->asAdmin()->postJson('/api/users', [
            'name'     => 'Doublon',
            'email'    => 'admin@igp.com', // déjà pris
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonStructure(['errors' => ['email']]);
    }

    #[Test]
    public function creation_echoue_si_champs_requis_manquants()
    {
        $this->asAdmin()->postJson('/api/users', [])->assertStatus(422);
    }

    #[Test]
    public function caissier_ne_peut_pas_creer_un_utilisateur()
    {
        $this->asCaissier()->postJson('/api/users', [
            'name'     => 'Tentative',
            'email'    => 'tentative@igp.com',
            'password' => 'secret123',
        ])->assertStatus(403);
    }

    // =========================================================================
    // PUT /api/users/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_modifier_le_nom_et_lemail()
    {
        $response = $this->asAdmin()->putJson("/api/users/{$this->caissier->id}", [
            'name'  => 'Nouveau Nom',
            'email' => 'modifie@igp.com',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('email', 'modifie@igp.com');

        $this->assertDatabaseHas('users', ['email' => 'modifie@igp.com']);
    }

    #[Test]
    public function admin_peut_changer_le_mot_de_passe()
    {
        $this->asAdmin()->putJson("/api/users/{$this->caissier->id}", [
            'password' => 'nouveau-mdp-securise',
        ])->assertStatus(200);

        $this->caissier->refresh();
        $this->assertTrue(Hash::check('nouveau-mdp-securise', $this->caissier->password));
    }

    #[Test]
    public function modification_retourne_404_pour_utilisateur_inexistant()
    {
        $this->asAdmin()->putJson('/api/users/99999', ['name' => 'X'])->assertStatus(404);
    }

    #[Test]
    public function caissier_ne_peut_pas_modifier_un_utilisateur()
    {
        $this->asCaissier()->putJson("/api/users/{$this->admin->id}", [
            'name' => 'Hack',
        ])->assertStatus(403);
    }

    // =========================================================================
    // DELETE /api/users/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_supprimer_un_utilisateur()
    {
        $pos  = PointOfSale::factory()->create();
        $cible = User::factory()->create(['point_of_sale_id' => $pos->id]);

        $response = $this->asAdmin()->deleteJson("/api/users/{$cible->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Utilisateur supprimé');

        $this->assertDatabaseMissing('users', ['id' => $cible->id]);
    }

    #[Test]
    public function suppression_retourne_404_pour_utilisateur_inexistant()
    {
        $this->asAdmin()->deleteJson('/api/users/99999')->assertStatus(404);
    }

    #[Test]
    public function caissier_ne_peut_pas_supprimer_un_utilisateur()
    {
        $this->asCaissier()
             ->deleteJson("/api/users/{$this->admin->id}")
             ->assertStatus(403);
    }
}
