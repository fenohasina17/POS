<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\{User, Sale, SalePayment, Payment, PointOfSale, CashRegister, CashRegisterSession, Product, Category, OrderLine};
use App\Services\CashRegisterSessionSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\{Permission, Role};

class CashRegisterSessionSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;
    protected $session;
    protected $paymentCash;
    protected $pos; // On stocke le POS pour le réutiliser

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CashRegisterSessionSummaryService();
        $this->paymentCash = Payment::factory()->create(['name' => 'Espèces']);

        // 1. Création d'UN SEUL POS pour tout le test
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
     * Authentification liée au POS de la session
     */
    private function authenticate(string $permissionName = 'view-session-summary', string $roleName = 'gerant')
    {
        // On utilise le POS créé au début pour que l'utilisateur ait accès aux mêmes données
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

        return [$user, $response->json('token') ?? $response->json('access_token')];
    }

    protected function createTestData()
    {
        $category = Category::factory()->create(['name' => 'Boissons']);
        $product = Product::factory()->create(['name' => 'Coca', 'category_id' => $category->id]);

        // On crée une vente de 19 000 liée à la session
        $sale = Sale::factory()->create([
            'cash_register_session_id' => $this->session->id,
            'final_amount' => 19000,
            'status' => 'completed'
        ]);

        OrderLine::factory()->create([
            'sale_id' => $sale->id, 
            'product_id' => $product->id, 
            'total' => 19000,
            'quantity' => 1,
            'price' => 19000
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

        // Si tu as encore 100, regarde le dump ici :
        if($response->json('total_sales') != 19000) {
            dump($response->json());
        }

        $response->assertStatus(200)
                 ->assertJsonPath('total_sales', 19000);
    }

    #[Test]
    public function it_calculates_correct_cash_difference_for_admin()
    {
        [$user, $token] = $this->authenticate('view-session-summary', 'admin');
        
        $this->actingAs($user, 'sanctum');
        $summary = $this->service->build($this->session);

        $this->assertEquals(131000, $summary['admin_finance']['cash_difference']);
    }

    #[Test]
    public function it_hides_finance_section_for_non_admin()
    {
        [$user, $token] = $this->authenticate('view-session-summary', 'gerant');
        
        $this->actingAs($user, 'sanctum');
        $summary = $this->service->build($this->session);

        $this->assertArrayNotHasKey('admin_finance', $summary);
    }
}