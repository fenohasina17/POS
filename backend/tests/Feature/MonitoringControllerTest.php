<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MonitoringControllerTest extends TestCase
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

        $this->pos     = PointOfSale::factory()->create(['name' => 'POS Monitoring']);
        $this->admin   = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->caissier = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->caissier->assignRole('caissier');
        $this->caissierToken = $this->caissier->createToken('test')->plainTextToken;

        // Crée une vente avec ligne de commande
        $category = Category::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $product  = Product::factory()->create(['category_id' => $category->id]);
        $sale     = Sale::factory()->create([
            'user_id'          => $this->admin->id,
            'point_of_sale_id' => $this->pos->id,
            'final_amount'     => 5000,
            'total_amount'     => 5000,
        ]);
        OrderLine::create([
            'sale_id'    => $sale->id,
            'product_id' => $product->id,
            'quantity'   => 5,
            'price'      => 1000,
            'total'      => 5000,
        ]);
    }

    private function asAdmin(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    // =========================================================================
    // GET /api/admin/monitoring
    // =========================================================================

    #[Test]
    public function admin_peut_acceder_au_monitoring()
    {
        $response = $this->asAdmin()->getJson('/api/admin/monitoring');

        $response->assertStatus(200);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_acceder_au_monitoring()
    {
        $this->getJson('/api/admin/monitoring')->assertStatus(401);
    }

    #[Test]
    public function caissier_ne_peut_pas_acceder_au_monitoring()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->caissierToken)
             ->getJson('/api/admin/monitoring')
             ->assertStatus(403);
    }

    #[Test]
    public function monitoring_retourne_les_kpis()
    {
        $response = $this->asAdmin()
             ->getJson("/api/admin/monitoring?pos_id={$this->pos->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'kpis' => [
                         'total_sales',
                         'total_revenue',
                         'average_ticket',
                         'total_discounts',
                     ],
                     'payment_summary',
                     'top_products',
                     'flop_products',
                     'category_summary',
                     'cashier_performance',
                     'sales_evolution',
                 ]);
    }

    #[Test]
    public function monitoring_global_groupe_par_pos()
    {
        PointOfSale::factory()->create(['name' => 'POS 2']);

        $response = $this->asAdmin()->getJson('/api/admin/monitoring');

        $response->assertStatus(200)
                 ->assertJsonIsArray();

        $posNames = collect($response->json())->pluck('pos_name');
        $this->assertTrue($posNames->contains('POS Monitoring'));
    }

    #[Test]
    public function monitoring_filtre_par_pos()
    {
        $response = $this->asAdmin()
             ->getJson("/api/admin/monitoring?pos_id={$this->pos->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('kpis.total_sales', 1)
                 ->assertJsonPath('kpis.total_revenue', 5000);
    }

    #[Test]
    public function monitoring_filtre_par_date()
    {
        $today = now()->format('Y-m-d');

        $response = $this->asAdmin()
             ->getJson("/api/admin/monitoring?pos_id={$this->pos->id}&start_date={$today}&end_date={$today}");

        $response->assertStatus(200)
                 ->assertJsonPath('kpis.total_sales', 1);
    }

    #[Test]
    public function monitoring_filtre_date_future_retourne_zero_ventes()
    {
        $future = now()->addYears(10)->format('Y-m-d');

        $response = $this->asAdmin()
             ->getJson("/api/admin/monitoring?pos_id={$this->pos->id}&start_date={$future}&end_date={$future}");

        $response->assertStatus(200)
                 ->assertJsonPath('kpis.total_sales', 0)
                 ->assertJsonPath('kpis.total_revenue', 0);
    }
}
