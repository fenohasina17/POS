<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OrderLine;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SalesExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;
    private PointOfSale $pos;
    private Product $product;
    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');

        $this->pos      = PointOfSale::factory()->create(['name' => 'POS Test']);
        $this->admin    = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $category      = Category::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Produit A']);

        $this->sale = Sale::factory()->create([
            'user_id'          => $this->admin->id,
            'point_of_sale_id' => $this->pos->id,
            'final_amount'     => 3000,
        ]);

        OrderLine::create([
            'sale_id'    => $this->sale->id,
            'product_id' => $this->product->id,
            'quantity'   => 3,
            'price'      => 1000,
            'total'      => 3000,
        ]);
    }

    private function asAdmin(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    // =========================================================================
    // GET /api/sales/export
    // =========================================================================

    #[Test]
    public function non_authentifie_ne_peut_pas_exporter()
    {
        $this->getJson('/api/sales/export')->assertStatus(401);
    }

    #[Test]
    public function export_sans_filtres_retourne_csv_avec_les_ventes()
    {
        $response = $this->asAdmin()->get('/api/sales/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function export_filtre_par_point_de_vente()
    {
        $autrePos  = PointOfSale::factory()->create();
        $autreSale = Sale::factory()->create([
            'user_id'          => $this->admin->id,
            'point_of_sale_id' => $autrePos->id,
        ]);

        $response = $this->asAdmin()
             ->get("/api/sales/export?pointOfSaleId={$this->pos->id}");

        $response->assertStatus(200);
        $content = $response->streamedContent();
        $this->assertStringContainsString('POS Test', $content);
    }

    #[Test]
    public function export_filtre_par_date()
    {
        $today = now()->format('Y-m-d');

        $response = $this->asAdmin()
             ->get("/api/sales/export?day={$today}");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function export_filtre_par_plage_de_dates()
    {
        $start = now()->subDays(7)->format('Y-m-d');
        $end   = now()->format('Y-m-d');

        $response = $this->asAdmin()
             ->get("/api/sales/export?startDate={$start}&endDate={$end}");

        $response->assertStatus(200);
    }

    #[Test]
    public function export_filtre_par_produit()
    {
        $response = $this->asAdmin()
             ->get("/api/sales/export?productId={$this->product->id}");

        $response->assertStatus(200);
        $content = $response->streamedContent();
        $this->assertStringContainsString('Produit A', $content);
    }

    #[Test]
    public function export_retourne_404_si_aucune_vente_trouvee()
    {
        $futureDate = now()->addYears(10)->format('Y-m-d');

        $this->asAdmin()
             ->getJson("/api/sales/export?day={$futureDate}")
             ->assertStatus(404)
             ->assertJsonFragment(['message' => 'Aucune vente trouvée.']);
    }

    #[Test]
    public function export_retourne_422_si_filtre_invalide()
    {
        $this->asAdmin()
             ->getJson('/api/sales/export?day=date-invalide')
             ->assertStatus(422);
    }

    #[Test]
    public function export_filtre_par_mois()
    {
        $month = now()->format('Y-m');

        $response = $this->asAdmin()
             ->get("/api/sales/export?month={$month}");

        $response->assertStatus(200);
    }

    #[Test]
    public function export_filtre_par_annee()
    {
        $year = now()->format('Y');

        $response = $this->asAdmin()
             ->get("/api/sales/export?year={$year}");

        $response->assertStatus(200);
    }

    #[Test]
    public function export_filtre_par_semaine()
    {
        $week = now()->format('Y') . '-W' . now()->format('W');

        $response = $this->asAdmin()
             ->get("/api/sales/export?week={$week}");

        $response->assertStatus(200);
    }
}
