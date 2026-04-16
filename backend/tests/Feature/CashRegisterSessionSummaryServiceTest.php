<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\{User, Sale, SalePayment, Payment, PointOfSale, CashRegister, CashRegisterSession, Product, Category, OrderLine};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\{Permission, Role};

class CashRegisterSessionSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $session;
    protected $paymentCash;
    protected $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentCash = Payment::factory()->create(['name' => 'Espèces']);

        // 1. On crée UN SEUL POS pour tout le test
        $this->pos = PointOfSale::factory()->create();
        $cashRegister = CashRegister::factory()->create(['point_of_sale_id' => $this->pos->id]);
        
        // 2. Création de la session liée à ce POS
        $this->session = CashRegisterSession::factory()->create([
            'cash_register_id' => $cashRegister->id,
            'starting_amount' => 100000,
            'actual_cash_amount' => 250000,
            'is_closed' => true,
            'user_id' => User::factory()->create(['point_of_sale_id' => $this->pos->id])->id
        ]);

        $this->createTestData();
    }

    /**
     * Authentification utilisant le POS de la session
     */
    private function authenticate(string $permissionName = 'view-session-summary', string $roleName = 'gerant')
    {
        // On lie l'utilisateur au même POS que la session
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'point_of_sale_id' => $this->pos->id
        ]);

        Permission::findOrCreate($permissionName, 'api');
        Permission::findOrCreate('view.cash_register_sessions', 'api');

        $role = Role::findOrCreate($roleName, 'api');
        $role->givePermissionTo([$permissionName, 'view.cash_register_sessions']);
        $user->assignRole($role);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);

        $token = $response->json('token') ?? $response->json('access_token');

        return [$user, $token];
    }

    protected function createTestData()
    {
        $category = Category::factory()->create(['name' => 'Boissons']);
        $product = Product::factory()->create(['name' => 'Coca', 'category_id' => $category->id]);

        // CRITIQUE : On force 'final_amount' à 19000 pour écraser le '100' du SaleFactory
        $sale = Sale::factory()->create([
            'cash_register_session_id' => $this->session->id,
            'point_of_sale_id' => $this->pos->id,
            'total_amount' => 19000,
            'final_amount' => 19000,
            'status' => 'completed'
        ]);

        OrderLine::factory()->create([
            'sale_id' => $sale->id, 
            'product_id' => $product->id, 
            'quantity' => 1,
            'price' => 19000,
            'total' => 19000
        ]);

        SalePayment::factory()->create([
            'sale_id' => $sale->id, 
            'payment_id' => $this->paymentCash->id, 
            'amount' => 19000
        ]);
    }

    #[Test]
    public function an_authorized_user_can_access_summary_api()
    {
        [$user, $token] = $this->authenticate('view-session-summary', 'gerant');

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->getJson("/api/cash-register-sessions/{$this->session->id}/summary");

        $response->assertStatus(200);
        $response->assertJsonFragment(['total_sales' => 19000]);
    }

    #[Test]
    public function an_unauthorized_user_is_forbidden_from_summary_api()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')
                         ->getJson("/api/cash-register-sessions/{$this->session->id}/summary");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_calculates_correct_cash_difference_for_admin()
    {
        [$user, $token] = $this->authenticate('view-session-summary', 'admin');
        
        $this->actingAs($user, 'sanctum');
        
        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->getJson("/api/cash-register-sessions/{$this->session->id}/summary");
        
        $response->assertStatus(200);
        
        // 100k (fond) + 19k (ventes) = 119k attendus. 250k réels. Diff = 131k.
        $this->assertEquals(131000, $response->json('admin_finance.cash_difference'));
    }

    #[Test]
    public function it_hides_finance_section_for_non_admin()
    {
        [$user, $token] = $this->authenticate('view-session-summary', 'gerant');
        
        $this->actingAs($user, 'sanctum');
        
        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->getJson("/api/cash-register-sessions/{$this->session->id}/summary");
        
        $response->assertStatus(200);
        $this->assertArrayNotHasKey('admin_finance', $response->json());
    }
}