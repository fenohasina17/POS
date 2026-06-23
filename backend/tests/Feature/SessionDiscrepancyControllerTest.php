<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\PointOfSale;
use App\Models\SessionDiscrepancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SessionDiscrepancyControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;
    private PointOfSale $pos;
    private CashRegisterSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');
        Role::findOrCreate('gérant', 'api');

        $this->pos   = PointOfSale::factory()->create();
        $this->admin = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $cashRegister  = CashRegister::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->session = CashRegisterSession::factory()->create([
            'cash_register_id' => $cashRegister->id,
            'user_id'          => $this->admin->id,
            'is_closed'        => true,
        ]);
    }

    private function asAdmin(): self
    {
        return $this->withHeaders([
            'Authorization'   => 'Bearer ' . $this->adminToken,
            'X-Active-POS-ID' => (string) $this->pos->id,
        ]);
    }

    private function createDiscrepancy(array $overrides = []): SessionDiscrepancy
    {
        return SessionDiscrepancy::create(array_merge([
            'session_id'        => $this->session->id,
            'difference_amount' => 500,
            'explanation'       => 'Erreur de caisse',
            'is_checked'        => false,
        ], $overrides));
    }

    // =========================================================================
    // GET /api/session-discrepancies
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_ecarts_non_verifies()
    {
        $this->createDiscrepancy(['difference_amount' => 1000]);
        $this->createDiscrepancy(['difference_amount' => 200]);
        $this->createDiscrepancy(['is_checked' => true, 'difference_amount' => 50]); // vérifié — doit être exclu

        $response = $this->asAdmin()->getJson('/api/session-discrepancies');

        $response->assertStatus(200)
                 ->assertJsonIsArray()
                 ->assertJsonCount(2);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_ecarts()
    {
        $this->getJson('/api/session-discrepancies')->assertStatus(401);
    }

    #[Test]
    public function utilisateur_sans_pos_actif_obtient_403()
    {
        $gerant = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $gerant->assignRole('gérant');
        $gerant->pointsOfSale()->attach($this->pos->id);
        $token = $gerant->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->getJson('/api/session-discrepancies')
             ->assertStatus(403);
    }

    // =========================================================================
    // PATCH /api/session-discrepancies/{id}/check
    // =========================================================================

    #[Test]
    public function admin_peut_valider_un_ecart()
    {
        $discrepancy = $this->createDiscrepancy();

        $response = $this->asAdmin()
             ->patchJson("/api/session-discrepancies/{$discrepancy->id}/check");

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Écart validé avec succès.']);

        $this->assertDatabaseHas('session_discrepancies', [
            'id'         => $discrepancy->id,
            'is_checked' => true,
        ]);
    }

    #[Test]
    public function validation_retourne_404_pour_ecart_inexistant()
    {
        $this->asAdmin()
             ->patchJson('/api/session-discrepancies/99999/check')
             ->assertStatus(404);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_valider_un_ecart()
    {
        $discrepancy = $this->createDiscrepancy();

        $this->patchJson("/api/session-discrepancies/{$discrepancy->id}/check")
             ->assertStatus(401);
    }

    #[Test]
    public function caissier_ne_peut_pas_valider_un_ecart()
    {
        Role::findOrCreate('caissier', 'api');
        $caissier = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $caissier->assignRole('caissier');
        $token = $caissier->createToken('test')->plainTextToken;

        $discrepancy = $this->createDiscrepancy();

        $this->withHeaders([
                 'Authorization'   => 'Bearer ' . $token,
                 'X-Active-POS-ID' => (string) $this->pos->id,
             ])
             ->patchJson("/api/session-discrepancies/{$discrepancy->id}/check")
             ->assertStatus(403);
    }
}
