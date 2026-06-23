<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');

        $pos         = PointOfSale::factory()->create();
        $this->admin = User::factory()->create(['point_of_sale_id' => $pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    private function asAdmin(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    // =========================================================================
    // GET /api/payments
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_modes_de_paiement()
    {
        Payment::factory()->create(['name' => 'Espèces']);
        Payment::factory()->create(['name' => 'Carte Bancaire']);

        $response = $this->asAdmin()->getJson('/api/payments');

        $response->assertStatus(200)
                 ->assertJsonIsArray()
                 ->assertJsonFragment(['name' => 'Espèces'])
                 ->assertJsonFragment(['name' => 'Carte Bancaire']);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_paiements()
    {
        $this->getJson('/api/payments')->assertStatus(401);
    }

    // =========================================================================
    // GET /api/payments/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_voir_un_mode_de_paiement()
    {
        $payment = Payment::factory()->create(['name' => 'Mvola']);

        $this->asAdmin()
             ->getJson("/api/payments/{$payment->id}")
             ->assertStatus(200)
             ->assertJsonPath('name', 'Mvola');
    }

    #[Test]
    public function retourne_404_pour_paiement_inexistant()
    {
        $this->asAdmin()
             ->getJson('/api/payments/99999')
             ->assertStatus(404);
    }

    // =========================================================================
    // POST /api/payments
    // =========================================================================

    #[Test]
    public function admin_peut_creer_un_mode_de_paiement()
    {
        $response = $this->asAdmin()->postJson('/api/payments', ['name' => 'Orange Money']);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'Orange Money');

        $this->assertDatabaseHas('payments', ['name' => 'Orange Money']);
    }

    #[Test]
    public function creation_echoue_si_nom_manquant()
    {
        $this->asAdmin()
             ->postJson('/api/payments', [])
             ->assertStatus(422);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_creer_un_paiement()
    {
        $this->postJson('/api/payments', ['name' => 'Test'])->assertStatus(401);
    }

    // =========================================================================
    // PUT /api/payments/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_modifier_un_mode_de_paiement()
    {
        $payment = Payment::factory()->create(['name' => 'Virement']);

        $this->asAdmin()
             ->putJson("/api/payments/{$payment->id}", ['name' => 'Virement Bancaire'])
             ->assertStatus(200)
             ->assertJsonPath('name', 'Virement Bancaire');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'name' => 'Virement Bancaire']);
    }

    #[Test]
    public function modification_retourne_404_pour_paiement_inexistant()
    {
        $this->asAdmin()
             ->putJson('/api/payments/99999', ['name' => 'X'])
             ->assertStatus(404);
    }

    // =========================================================================
    // DELETE /api/payments/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_supprimer_un_mode_de_paiement()
    {
        $payment = Payment::factory()->create(['name' => 'Espèces']);

        $this->asAdmin()
             ->deleteJson("/api/payments/{$payment->id}")
             ->assertStatus(200)
             ->assertJsonFragment(['message' => 'Payment type deleted successfully']);

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    #[Test]
    public function suppression_retourne_404_pour_paiement_inexistant()
    {
        $this->asAdmin()
             ->deleteJson('/api/payments/99999')
             ->assertStatus(404);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_supprimer_un_paiement()
    {
        $payment = Payment::factory()->create(['name' => 'Espèces']);
        $this->deleteJson("/api/payments/{$payment->id}")->assertStatus(401);
    }
}
