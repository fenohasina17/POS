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
 * « Sur place » / « À emporter » : choix demandé au client par le caissier, article
 * par article (une commande peut mélanger les deux), résumé sur la vente
 * (sur_place | a_emporter | mixte) et renvoyé pour le ticket et les bons.
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

    private function pendingSale(Table $table, int $quantity = 1, string $serviceType = 'sur_place'): Sale
    {
        $sale = Sale::create([
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pos->id,
            'cash_register_session_id' => $this->session->id,
            'table_id' => $table->id,
            'service_type' => $serviceType,
            'total_amount' => 100 * $quantity,
            'final_amount' => 100 * $quantity,
            'status' => 'pending',
            'ticket_number' => '001',
        ]);
        OrderLine::create([
            'sale_id' => $sale->id, 'product_id' => $this->product->id, 'quantity' => $quantity,
            'price' => 100, 'total' => 100 * $quantity, 'service_type' => $serviceType,
        ]);

        return $sale;
    }

    private function pendingOrderPayload(Table $table, array $lines, array $overrides = []): array
    {
        return array_merge([
            'table_id' => $table->id,
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pos->id,
            'cash_register_session_id' => $this->session->id,
            'order_lines' => $lines,
        ], $overrides);
    }

    #[Test]
    public function la_migration_ajoute_la_colonne_aux_ventes_et_aux_lignes()
    {
        $this->assertTrue(Schema::hasColumn('sales', 'service_type'));
        $this->assertTrue(Schema::hasColumn('order_lines', 'service_type'));
    }

    #[Test]
    public function une_vente_toute_a_emporter_marque_la_vente_et_ses_lignes()
    {
        $response = $this->api()->postJson('/api/sales', $this->directSalePayload(['service_type' => 'a_emporter']));

        $response->assertStatus(201)
            ->assertJsonPath('data.sale.service_type', 'a_emporter')
            ->assertJsonPath('data.categories.0.items.0.service_type', 'a_emporter');

        $saleId = $response->json('data.sale.id');
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'service_type' => 'a_emporter']);
        $this->assertDatabaseHas('order_lines', ['sale_id' => $saleId, 'service_type' => 'a_emporter']);
    }

    #[Test]
    public function une_meme_commande_peut_melanger_sur_place_et_a_emporter()
    {
        $payload = $this->directSalePayload([
            'total_amount' => 300,
            'final_amount' => 300,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100, 'total' => 200, 'service_type' => 'sur_place'],
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'service_type' => 'a_emporter'],
            ],
            'payments' => [['payment_id' => Payment::firstOrCreate(['name' => 'Espèces'])->id, 'amount' => 300]],
        ]);

        $response = $this->api()->postJson('/api/sales', $payload);

        $response->assertStatus(201)->assertJsonPath('data.sale.service_type', 'mixte');
        $saleId = $response->json('data.sale.id');
        $this->assertDatabaseHas('order_lines', ['sale_id' => $saleId, 'quantity' => 2, 'service_type' => 'sur_place']);
        $this->assertDatabaseHas('order_lines', ['sale_id' => $saleId, 'quantity' => 1, 'service_type' => 'a_emporter']);
        $this->assertEqualsCanonicalizing(
            ['sur_place', 'a_emporter'],
            collect($response->json('data.categories.0.items'))->pluck('service_type')->all()
        );
    }

    #[Test]
    public function le_choix_d_un_article_l_emporte_sur_le_choix_global()
    {
        $payload = $this->directSalePayload(['service_type' => 'sur_place']);
        $payload['items'][0]['service_type'] = 'a_emporter';

        $this->api()->postJson('/api/sales', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.sale.service_type', 'a_emporter');
    }

    #[Test]
    public function une_valeur_inconnue_est_refusee()
    {
        $this->api()->postJson('/api/sales', $this->directSalePayload(['service_type' => 'livraison']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('service_type');

        $payload = $this->directSalePayload();
        $payload['items'][0]['service_type'] = 'mixte'; // résumé seulement, pas un choix d'article
        $this->api()->postJson('/api/sales', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.service_type');
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

        $response = $this->api()->postJson('/api/sales/pending-order', $this->pendingOrderPayload($table, [
            ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 100],
        ]));

        $response->assertStatus(201)->assertJsonPath('service_type', 'sur_place');
        $this->assertDatabaseHas('order_lines', ['sale_id' => $response->json('id'), 'service_type' => 'sur_place']);
    }

    #[Test]
    public function une_commande_a_table_peut_avoir_des_articles_a_emporter()
    {
        $table = Table::factory()->create(['point_of_sale_id' => $this->pos->id, 'status' => 'available']);

        $response = $this->api()->postJson('/api/sales/pending-order', $this->pendingOrderPayload($table, [
            ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 100],
            ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 100, 'service_type' => 'a_emporter'],
        ]));

        $response->assertStatus(201)->assertJsonPath('service_type', 'mixte');
    }

    #[Test]
    public function une_nouvelle_tournee_a_emporter_ne_fusionne_pas_avec_le_meme_produit_sur_place()
    {
        $table = Table::factory()->create(['point_of_sale_id' => $this->pos->id, 'status' => 'occupied']);
        $sale = $this->pendingSale($table);

        $this->api()->postJson("/api/sales/{$sale->id}/add-products", [
            'order_lines' => [['product_id' => $this->product->id, 'quantity' => 1, 'price' => 100, 'service_type' => 'a_emporter']],
        ])->assertStatus(200);

        $this->assertSame(2, $sale->orderLines()->count());
        $this->assertSame('mixte', $sale->fresh()->service_type);
    }

    #[Test]
    public function a_l_encaissement_tout_peut_passer_a_emporter()
    {
        $table = Table::factory()->create(['point_of_sale_id' => $this->pos->id, 'status' => 'occupied']);
        $sale = $this->pendingSale($table);

        $this->api()->postJson("/api/sales/{$sale->id}/validate", [
            'payment_id' => Payment::firstOrCreate(['name' => 'Espèces'])->id,
            'amount_received' => 100,
            'service_type' => 'a_emporter',
        ])->assertStatus(200)->assertJsonPath('data.sale.service_type', 'a_emporter');

        $this->assertSame('a_emporter', $sale->fresh()->service_type);
        $this->assertSame(['a_emporter'], $sale->orderLines()->pluck('service_type')->unique()->values()->all());
    }

    #[Test]
    public function a_l_encaissement_une_partie_de_la_quantite_peut_partir_a_emporter()
    {
        $table = Table::factory()->create(['point_of_sale_id' => $this->pos->id, 'status' => 'occupied']);
        $sale = $this->pendingSale($table, 3);
        $lineId = $sale->orderLines()->value('id');

        $response = $this->api()->postJson("/api/sales/{$sale->id}/validate", [
            'payment_id' => Payment::firstOrCreate(['name' => 'Espèces'])->id,
            'amount_received' => 300,
            'line_splits' => [['order_line_id' => $lineId, 'a_emporter_quantity' => 1]],
        ]);

        $response->assertStatus(200)->assertJsonPath('data.sale.service_type', 'mixte');
        $this->assertDatabaseHas('order_lines', ['id' => $lineId, 'quantity' => 2, 'total' => 200, 'service_type' => 'sur_place']);
        $this->assertDatabaseHas('order_lines', ['sale_id' => $sale->id, 'quantity' => 1, 'total' => 100, 'service_type' => 'a_emporter']);
        $this->assertEquals(300, $sale->fresh()->final_amount); // le total ne change pas
    }

    #[Test]
    public function l_encaissement_sans_choix_garde_celui_de_la_commande()
    {
        $table = Table::factory()->create(['point_of_sale_id' => $this->pos->id, 'status' => 'occupied']);
        $sale = $this->pendingSale($table);

        $this->api()->postJson("/api/sales/{$sale->id}/validate", [
            'payment_id' => Payment::firstOrCreate(['name' => 'Espèces'])->id,
            'amount_received' => 100,
        ])->assertStatus(200);

        $this->assertSame('sur_place', $sale->fresh()->service_type);
    }
}
