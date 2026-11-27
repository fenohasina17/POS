<?php

namespace Tests\Feature;

use App\Models\{User, PointOfSale, Sale, Product, CashRegister, CashRegisterSession, OrderLine, Table, Payment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * « Sur place » / « À emporter » : choix demandé au client par le caissier,
 * enregistré sur la vente et renvoyé pour le ticket et les bons de préparation.
 */
class SaleServiceTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PointOfSale $pos;
    private CashRegisterSession $session;
    private Product $product;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('create.sales', 'api');
        Role::findOrCreate('caissier', 'api')->givePermissionTo('create.sales');

        $this->pos = PointOfSale::factory()->create();
        $this->user = User::factory()->create(['password' => bcrypt('password123')]);
        $this->user->pointsOfSale()->attach($this->pos->id);
        $this->user->assignRole('caissier');
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $register = CashRegister::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->session = CashRegisterSession::factory()->create([
            'user_id' => $this->user->id,
            'cash_register_id' => $register->id,
            'is_closed' => false,
            'total_sales' => 0,
        ]);

        $this->product = Product::factory()->create();
        $this->product->pointsOfSale()->attach($this->pos->id);

        $login = $this->postJson('/api/login', ['email' => $this->user->email, 'password' => 'password123']);
        $this->token = $login->json('token') ?? $login->json('access_token');
    }

    private function api()
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'X-Active-POS-ID' => (string) $this->pos->id,
        ]);
    }

    private function directSalePayload(array $overrides = []): array
    {
        $payment = Payment::firstOrCreate(['name' => 'Espèces']);

        return array_merge([
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pos->id,
            'cash_register_session_id' => $this->session->id,
            'total_amount' => 200,
            'final_amount' => 200,
            'status' => 'completed',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'total' => 200,
            ]],
            'payments' => [['payment_id' => $payment->id, 'amount' => 200]],
        ], $overrides);
    }

    #[Test]
    public function la_migration_ajoute_la_colonne_service_type_a_sales()
    {
        $this->assertTrue(Schema::hasColumn('sales', 'service_type'));
    }

    #[Test]
    public function une_vente_directe_a_emporter_est_enregistree_et_renvoyee()
    {
        $response = $this->api()->postJson('/api/sales', $this->directSalePayload(['service_type' => 'a_emporter']));

        $response->assertStatus(201)
            ->assertJsonPath('data.sale.service_type', 'a_emporter');

        $this->assertDatabaseHas('sales', [
            'id' => $response->json('data.sale.id'),
            'service_type' => 'a_emporter',
        ]);
    }

    #[Test]
    public function une_vente_directe_sur_place_est_enregistree()
    {
        $response = $this->api()->postJson('/api/sales', $this->directSalePayload(['service_type' => 'sur_place']));

        $response->assertStatus(201)->assertJsonPath('data.sale.service_type', 'sur_place');
    }

    #[Test]
    public function une_valeur_inconnue_est_refusee()
    {
        $this->api()->postJson('/api/sales', $this->directSalePayload(['service_type' => 'livraison']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('service_type');
    }

    #[Test]
    public function une_vente_sans_choix_reste_possible_pour_les_anciens_clients()
    {
        $response = $this->api()->postJson('/api/sales', $this->directSalePayload());

        $response->assertStatus(201)->assertJsonPath('data.sale.service_type', null);
    }

    #[Test]
    public function une_commande_a_table_est_sur_place_par_defaut()
    {
        $table = Table::factory()->create(['point_of_sale_id' => $this->pos->id, 'status' => 'available']);

        $response = $this->api()->postJson('/api/sales/pending-order', [
            'table_id' => $table->id,
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pos->id,
            'cash_register_session_id' => $this->session->id,
            'order_lines' => [['product_id' => $this->product->id, 'quantity' => 1, 'price' => 100]],
        ]);

        $response->assertStatus(201)->assertJsonPath('service_type', 'sur_place');
    }

    #[Test]
    public function une_commande_a_table_peut_etre_a_emporter()
    {
        $table = Table::factory()->create(['point_of_sale_id' => $this->pos->id, 'status' => 'available']);

        $response = $this->api()->postJson('/api/sales/pending-order', [
            'table_id' => $table->id,
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pos->id,
            'cash_register_session_id' => $this->session->id,
            'service_type' => 'a_emporter',
            'order_lines' => [['product_id' => $this->product->id, 'quantity' => 1, 'price' => 100]],
        ]);

        $response->assertStatus(201)->assertJsonPath('service_type', 'a_emporter');
    }

    #[Test]
    public function le_caissier_peut_changer_le_choix_a_l_encaissement_de_la_table()
    {
        $table = Table::factory()->create(['point_of_sale_id' => $this->pos->id, 'status' => 'occupied']);
        $payment = Payment::firstOrCreate(['name' => 'Espèces']);

        $sale = Sale::create([
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pos->id,
            'cash_register_session_id' => $this->session->id,
            'table_id' => $table->id,
            'service_type' => 'sur_place',
            'total_amount' => 100,
            'final_amount' => 100,
            'status' => 'pending',
            'ticket_number' => '001',
        ]);
        OrderLine::create(['sale_id' => $sale->id, 'product_id' => $this->product->id, 'quantity' => 1, 'price' => 100, 'total' => 100]);

        $this->api()->postJson("/api/sales/{$sale->id}/validate", [
            'payment_id' => $payment->id,
            'amount_received' => 100,
            'service_type' => 'a_emporter',
        ])->assertStatus(200)->assertJsonPath('data.sale.service_type', 'a_emporter');

        $this->assertSame('a_emporter', $sale->fresh()->service_type);
    }

    #[Test]
    public function l_encaissement_sans_choix_garde_celui_de_la_commande()
    {
        $table = Table::factory()->create(['point_of_sale_id' => $this->pos->id, 'status' => 'occupied']);
        $payment = Payment::firstOrCreate(['name' => 'Espèces']);

        $sale = Sale::create([
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pos->id,
            'cash_register_session_id' => $this->session->id,
            'table_id' => $table->id,
            'service_type' => 'sur_place',
            'total_amount' => 100,
            'final_amount' => 100,
            'status' => 'pending',
            'ticket_number' => '001',
        ]);
        OrderLine::create(['sale_id' => $sale->id, 'product_id' => $this->product->id, 'quantity' => 1, 'price' => 100, 'total' => 100]);

        $this->api()->postJson("/api/sales/{$sale->id}/validate", [
            'payment_id' => $payment->id,
            'amount_received' => 100,
        ])->assertStatus(200);

        $this->assertSame('sur_place', $sale->fresh()->service_type);
    }
}
