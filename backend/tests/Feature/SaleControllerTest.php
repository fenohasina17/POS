<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, PointOfSale, Sale, Product, CashRegister, CashRegisterSession, OrderLine, Table, Payment};
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

class SaleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin', 'api');
        Role::findOrCreate('caissier', 'api');
        Role::findOrCreate('gerant', 'api');

        Permission::findOrCreate('view.sales', 'api');
        Permission::findOrCreate('create.sales', 'api');
        Permission::findOrCreate('edit.sales', 'api');
        Permission::findOrCreate('delete.sales', 'api');
        Permission::findOrCreate('view.cash_register_sessions', 'api');

        $adminRole = Role::findByName('admin', 'api');
        $adminRole->givePermissionTo(['view.sales', 'create.sales', 'edit.sales', 'delete.sales', 'view.cash_register_sessions']);

        $caissierRole = Role::findByName('caissier', 'api');
        $caissierRole->givePermissionTo(['view.sales', 'create.sales']);

        $gerantRole = Role::findByName('gerant', 'api');
        $gerantRole->givePermissionTo(['view.sales', 'create.sales']);
    }

    private function authenticate(string $permission = null)
    {
        $pos = PointOfSale::factory()->create();
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'point_of_sale_id' => $pos->id
        ]);

        if ($permission) {
            Permission::findOrCreate($permission, 'api');
            $user->givePermissionTo($permission);
        }

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);

        $token = $response->json('token') ?? $response->json('access_token');

        return [$user, $token, $pos];
    }

    #[Test]
    public function admin_can_see_all_sales()
    {
        [$user, $token] = $this->authenticate();
        $user->assignRole(Role::findByName('admin', 'api'));
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/sales');

        $response->assertStatus(200);
    }

    #[Test]
    public function cashier_can_only_see_their_own_sales()
    {
        [$cashier, $token] = $this->authenticate();
        $cashier->assignRole(Role::findByName('caissier', 'api'));
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        Sale::factory()->create(['user_id' => $cashier->id]);
        Sale::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/sales');

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    #[Test]
    public function admin_can_access_product_kpis_for_any_pos()
    {
        [$admin, $token, $adminPos] = $this->authenticate();
        $admin->assignRole(Role::findByName('admin', 'api'));
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $targetPos = PointOfSale::factory()->create();
        $product = Product::factory()->create(['name' => 'Café']);

        $sale = Sale::factory()->create([
            'point_of_sale_id' => $targetPos->id,
            'status' => 'completed',
            'created_at' => now(),
        ]);

        OrderLine::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'total' => 500,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson("/api/point-of-sales/{$targetPos->id}/product-kpis");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Café']);
    }

    #[Test]
    public function manager_cannot_access_kpis_of_another_pos()
    {
        $pos1 = PointOfSale::factory()->create();
        $pos2 = PointOfSale::factory()->create();

        $manager = User::factory()->create(['point_of_sale_id' => $pos1->id]);
        $manager->assignRole('gerant');

        $token = $manager->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/point-of-sales/{$pos2->id}/product-kpis");

        $response->assertStatus(403);
    }

 // tests/Feature/SaleControllerTest.php

#[Test]
public function user_is_manager_logic()
{
    $saleServiceMock = $this->createMock(\App\Services\SaleService::class);
    $printGroupingServiceMock = $this->createMock(\App\Services\PrintGroupingService::class);
    $cashTransactionServiceMock = $this->createMock(\App\Services\CashTransactionService::class);

    $controller = new \App\Http\Controllers\SaleController($saleServiceMock, $printGroupingServiceMock, $cashTransactionServiceMock);

    $reflection = new ReflectionClass(get_class($controller));
    $method = $reflection->getMethod('userIsManager');
    $method->setAccessible(true);

    Role::findOrCreate('gerant', 'api');
    Role::findOrCreate('caissier', 'api');

    $manager = User::factory()->create();
    $manager->assignRole(Role::findByName('gerant', 'api'));

    $caissier = User::factory()->create();
    $caissier->assignRole(Role::findByName('caissier', 'api'));

    $this->assertTrue($method->invoke($controller, $manager));
    $this->assertFalse($method->invoke($controller, $caissier));
}
    #[Test]
    public function admin_can_create_a_sale()
    {
        [$user, $token, $pos] = $this->authenticate();
        $user->assignRole(Role::findByName('admin', 'api'));
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);

        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => false,
            'start_ticket_number' => 1
        ]);

        $table = Table::factory()->create([
            'point_of_sale_id' => $pos->id,
            'status' => 'available'
        ]);

        $product = Product::factory()->create();

        $payload = [
            'table_id' => $table->id,
            'user_id' => $user->id,
            'point_of_sale_id' => $pos->id,
            'cash_register_session_id' => $session->id,
            'discount_percentage' => 0,
            'order_lines' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 100.00,
                ]
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/sales/pending-order', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sales', [
            'table_id' => $table->id,
            'status' => 'pending'
        ]);

        $this->assertDatabaseHas('order_lines', [
            'product_id' => $product->id,
            'price' => 100.00
        ]);
    }

    #[Test]
    public function cashier_can_create_completed_sale()
    {
        [$user, $token, $pos] = $this->authenticate();
        $user->assignRole(Role::findByName('caissier', 'api'));
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => false
        ]);

        $table = Table::factory()->create([
            'point_of_sale_id' => $pos->id,
            'status' => 'available'
        ]);

        $product = Product::factory()->create();
        $payment = Payment::create(['name' => 'Espèces']);

        $quantity = 2;
        $unitPrice = 100;
        $total = $quantity * $unitPrice;

        $payload = [
            'table_id' => $table->id,
            'user_id' => $user->id,
            'point_of_sale_id' => $pos->id,
            'cash_register_session_id' => $session->id,
            'discount_percentage' => 0,
            'total_amount' => $total,
            'final_amount' => $total,
            'amount_received' => $total,
            'change_returned' => 0,
            'payment_id' => $payment->id,
            'status' => 'completed',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'price' => $unitPrice,
                    'total' => $total
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->postJson('/api/sales', $payload);

        $response->assertStatus(201);
    }

    #[Test]
    public function cancel_sale()
    {
        [$user, $token, $pos] = $this->authenticate();
        $user->assignRole('admin');

        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => false,
            'total_sales' => 0
        ]);

        $table = Table::factory()->create([
            'point_of_sale_id' => $pos->id,
            'status' => 'available'
        ]);

        $product = Product::factory()->create();
        $payment = Payment::create(['name' => 'Espèces']);

        $sale = Sale::create([
            'user_id' => $user->id,
            'point_of_sale_id' => $pos->id,
            'cash_register_session_id' => $session->id,
            'table_id' => $table->id,
            'total_amount' => 200,
            'final_amount' => 200,
            'status' => 'completed',
            'ticket_number' => '001',
            'amount_received' => 200,
            'change_amount' => 0
        ]);

        OrderLine::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100,
            'total' => 200
        ]);

        $session->increment('total_sales', 200);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->postJson("/api/sales/{$sale->id}/cancel", ['reason' => 'Test annulation']);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Test annulation'
        ]);
    }

    #[Test]
    public function add_to_pending_order()
    {
        [$user, $token, $pos] = $this->authenticate();
        $user->assignRole('admin');

        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => false
        ]);

        $table = Table::factory()->create([
            'point_of_sale_id' => $pos->id,
            'status' => 'available'
        ]);

        $product = Product::factory()->create();

        $sale = Sale::create([
            'user_id' => $user->id,
            'point_of_sale_id' => $pos->id,
            'cash_register_session_id' => $session->id,
            'table_id' => $table->id,
            'total_amount' => 0,
            'final_amount' => 0,
            'status' => 'pending',
            'ticket_number' => '001'
        ]);

        $payload = [
            'order_lines' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 100.00,
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->postJson("/api/sales/{$sale->id}/add-products", $payload);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('order_lines', [
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2
        ]);
    }

    #[Test]
    public function remove_from_pending_order()
    {
        [$user, $token, $pos] = $this->authenticate();
        $user->assignRole('admin');

        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => false
        ]);

        $table = Table::factory()->create([
            'point_of_sale_id' => $pos->id,
            'status' => 'available'
        ]);

        $product = Product::factory()->create();

        $sale = Sale::create([
            'user_id' => $user->id,
            'point_of_sale_id' => $pos->id,
            'cash_register_session_id' => $session->id,
            'table_id' => $table->id,
            'total_amount' => 200,
            'final_amount' => 200,
            'status' => 'pending',
            'ticket_number' => '001'
        ]);

        $orderLine = OrderLine::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100,
            'total' => 200
        ]);

        $payload = [
            'order_line_ids' => [$orderLine->id]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->postJson("/api/sales/{$sale->id}/remove-products", $payload);

        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('order_lines', ['id' => $orderLine->id]);
    }

    #[Test]
    public function validate_pending_order()
    {
        [$user, $token, $pos] = $this->authenticate();
        $user->assignRole('admin');

        $register = CashRegister::factory()->create(['point_of_sale_id' => $pos->id]);
        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'is_closed' => false,
            'total_sales' => 0
        ]);

        $table = Table::factory()->create([
            'point_of_sale_id' => $pos->id,
            'status' => 'occupied'
        ]);

        $product = Product::factory()->create();
        $payment = Payment::create(['name' => 'Espèces']);

        $sale = Sale::create([
            'user_id' => $user->id,
            'point_of_sale_id' => $pos->id,
            'cash_register_session_id' => $session->id,
            'table_id' => $table->id,
            'total_amount' => 200,
            'final_amount' => 200,
            'status' => 'pending',
            'ticket_number' => '001'
        ]);

        OrderLine::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100,
            'total' => 200
        ]);

        $payload = [
            'payment_id' => $payment->id,
            'discount_percentage' => 0,
            'amount_received' => 200,
            'change_amount' => 0
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->postJson("/api/sales/{$sale->id}/validate", $payload);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'completed'
        ]);
        
        $table->refresh();
        $this->assertEquals('available', $table->status);
        
        $session->refresh();
        $this->assertEquals(200, $session->total_sales);
    }
}