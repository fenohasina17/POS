<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CashRegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $caissier;
    private string $adminToken;
    private string $caissierToken;
    private PointOfSale $pos;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');
        Role::findOrCreate('caissier', 'api');

        $this->pos = PointOfSale::factory()->create();

        $this->admin = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->caissier = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->caissier->assignRole('caissier');
        $this->caissier->pointsOfSale()->attach($this->pos->id);
        $this->caissierToken = $this->caissier->createToken('test')->plainTextToken;
    }

    private function asAdmin(): self
    {
        return $this->withHeaders([
            'Authorization'   => 'Bearer ' . $this->adminToken,
            'X-Active-POS-ID' => (string) $this->pos->id,
        ]);
    }

    private function asCaissier(): self
    {
        return $this->withHeaders([
            'Authorization'   => 'Bearer ' . $this->caissierToken,
            'X-Active-POS-ID' => (string) $this->pos->id,
        ]);
    }

    private function createCashRegister(array $overrides = []): CashRegister
    {
        return CashRegister::factory()->create(array_merge([
            'point_of_sale_id' => $this->pos->id,
        ], $overrides));
    }

    // =========================================================================
    // GET /api/cash-registers
    // =========================================================================

    #[Test]
    public function admin_peut_lister_toutes_les_caisses()
    {
        $this->createCashRegister(['name' => 'Caisse A']);
        $this->createCashRegister(['name' => 'Caisse B']);

        $response = $this->asAdmin()->getJson('/api/cash-registers');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonFragment(['name' => 'Caisse A'])
                 ->assertJsonFragment(['name' => 'Caisse B']);
    }

    #[Test]
    public function caissier_peut_lister_les_caisses_de_son_pos_actif()
    {
        $this->createCashRegister(['name' => 'Caisse POS']);

        $autrePos = PointOfSale::factory()->create();
        CashRegister::factory()->create(['point_of_sale_id' => $autrePos->id, 'name' => 'Caisse Autre']);

        $response = $this->asCaissier()->getJson('/api/cash-registers');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonFragment(['name' => 'Caisse POS']);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertFalse($names->contains('Caisse Autre'));
    }

    #[Test]
    public function caissier_sans_pos_actif_obtient_403()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->caissierToken)
             ->getJson('/api/cash-registers')
             ->assertStatus(403);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_caisses()
    {
        $this->getJson('/api/cash-registers')->assertStatus(401);
    }

    // =========================================================================
    // GET /api/cash-registers/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_voir_une_caisse()
    {
        $caisse = $this->createCashRegister(['name' => 'Caisse Test']);

        $this->asAdmin()
             ->getJson("/api/cash-registers/{$caisse->id}")
             ->assertStatus(200)
             ->assertJsonPath('name', 'Caisse Test');
    }

    #[Test]
    public function retourne_404_pour_caisse_inexistante()
    {
        $this->asAdmin()
             ->getJson('/api/cash-registers/99999')
             ->assertStatus(404);
    }

    // =========================================================================
    // POST /api/cash-registers
    // =========================================================================

    #[Test]
    public function admin_peut_creer_une_caisse()
    {
        $response = $this->asAdmin()->postJson('/api/cash-registers', [
            'name'             => 'Caisse Principale',
            'point_of_sale_id' => $this->pos->id,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Caisse Principale');

        $this->assertDatabaseHas('cash_registers', [
            'name'             => 'Caisse Principale',
            'point_of_sale_id' => $this->pos->id,
        ]);
    }

    #[Test]
    public function caissier_peut_creer_une_caisse_dans_son_pos_actif()
    {
        $response = $this->asCaissier()->postJson('/api/cash-registers', [
            'name' => 'Caisse Caissier',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Caisse Caissier');
    }

    #[Test]
    public function creation_echoue_si_nom_manquant()
    {
        $this->asAdmin()
             ->postJson('/api/cash-registers', ['point_of_sale_id' => $this->pos->id])
             ->assertStatus(422);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_creer_une_caisse()
    {
        $this->postJson('/api/cash-registers', ['name' => 'Test'])
             ->assertStatus(401);
    }

    // =========================================================================
    // PUT /api/cash-registers/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_modifier_une_caisse()
    {
        $caisse = $this->createCashRegister(['name' => 'Ancienne Caisse']);

        $this->asAdmin()
             ->putJson("/api/cash-registers/{$caisse->id}", ['name' => 'Nouvelle Caisse'])
             ->assertStatus(200)
             ->assertJsonPath('data.name', 'Nouvelle Caisse');

        $this->assertDatabaseHas('cash_registers', ['id' => $caisse->id, 'name' => 'Nouvelle Caisse']);
    }

    #[Test]
    public function modification_retourne_404_pour_caisse_inexistante()
    {
        $this->asAdmin()
             ->putJson('/api/cash-registers/99999', ['name' => 'X'])
             ->assertStatus(404);
    }

    // =========================================================================
    // DELETE /api/cash-registers/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_supprimer_une_caisse()
    {
        $caisse = $this->createCashRegister();

        $this->asAdmin()
             ->deleteJson("/api/cash-registers/{$caisse->id}")
             ->assertStatus(200)
             ->assertJsonFragment(['message' => 'Caisse supprimée avec succès']);

        $this->assertDatabaseMissing('cash_registers', ['id' => $caisse->id]);
    }

    #[Test]
    public function suppression_retourne_404_pour_caisse_inexistante()
    {
        $this->asAdmin()
             ->deleteJson('/api/cash-registers/99999')
             ->assertStatus(404);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_supprimer_une_caisse()
    {
        $caisse = $this->createCashRegister();
        $this->deleteJson("/api/cash-registers/{$caisse->id}")->assertStatus(401);
    }

    // =========================================================================
    // GET /api/cash-registers/client-ip
    // =========================================================================

    #[Test]
    public function retourne_l_ip_du_client()
    {
        $this->asAdmin()
             ->getJson('/api/cash-registers/client-ip')
             ->assertStatus(200)
             ->assertJsonStructure(['ip', 'detected_at']);
    }

    // =========================================================================
    // GET /api/cash-registers/{id}/current-session
    // =========================================================================

    #[Test]
    public function admin_peut_voir_la_session_active_d_une_caisse()
    {
        $caisse  = $this->createCashRegister();
        $session = CashRegisterSession::factory()->create([
            'cash_register_id' => $caisse->id,
            'user_id'          => $this->admin->id,
            'is_closed'        => false,
        ]);

        $response = $this->asAdmin()
             ->getJson("/api/cash-registers/{$caisse->id}/current-session");

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.id', $session->id);
    }
}
