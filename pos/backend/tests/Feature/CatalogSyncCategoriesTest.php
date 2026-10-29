<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PointOfSale;
use App\Models\Pricing;
use App\Models\Product;
use App\Services\CatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Recatégorisation au Central : imprimantes et catégories côté caisse.
 */
class CatalogSyncCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private PointOfSale $pos;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.central_url'   => 'https://central.test',
            'sync.api_key'       => 'cle-test',
            'sync.restaurant_id' => '101',
        ]);

        $this->pos = PointOfSale::factory()->create(['name' => '101']);
    }

    private function product(Category $category, string $ref): Product
    {
        $product = Product::factory()->create(['category_id' => $category->id, 'ref' => $ref]);
        $this->pos->products()->attach($product->id);
        Pricing::create(['product_id' => $product->id, 'point_of_sale_id' => $this->pos->id, 'price' => 1000, 'central_price' => 1000]);

        return $product;
    }

    private function pull(array $categories, array $products): void
    {
        Http::fake([
            'central.test/api/catalog' => Http::response(['categories' => $categories, 'products' => $products]),
        ]);
        app(CatalogSyncService::class)->pull();
    }

    private function row(Product $product, string $category): array
    {
        return ['ref' => $product->ref, 'name' => $product->name, 'category' => $category, 'price' => 1000];
    }

    #[Test]
    public function une_nouvelle_categorie_reprend_l_imprimante_des_produits_deplaces()
    {
        $boisson = Category::factory()->create(['name' => 'Boisson', 'printer' => 'bar']);
        $jus = $this->product($boisson, 'BO01');

        $this->pull(['Jus'], [$this->row($jus, 'Jus')]);

        $this->assertSame('bar', Category::where('name', 'Jus')->value('printer'));
        $this->assertSame('Jus', $jus->fresh()->category->name);
    }

    #[Test]
    public function un_reglage_d_imprimante_existant_n_est_jamais_ecrase()
    {
        $boisson = Category::factory()->create(['name' => 'Boisson', 'printer' => 'bar']);
        Category::factory()->create(['name' => 'Jus', 'printer' => 'kitchen']);
        $jus = $this->product($boisson, 'BO01');

        $this->pull(['Jus'], [$this->row($jus, 'Jus')]);

        $this->assertSame('kitchen', Category::where('name', 'Jus')->value('printer'));
    }

    #[Test]
    public function les_categories_videes_et_absentes_du_central_sont_retirees()
    {
        $pizza = Category::factory()->create(['name' => 'Pizza']);
        $locale = Category::factory()->create(['name' => 'Spécial maison']);
        $p1 = $this->product($pizza, 'PZ01');
        $this->product($locale, 'LOC1');

        $this->pull(['Pizza GM'], [$this->row($p1, 'Pizza GM')]);

        $this->assertDatabaseMissing('categories', ['name' => 'Pizza']);
        // Absente du Central mais pas vide : conservée.
        $this->assertDatabaseHas('categories', ['name' => 'Spécial maison']);
    }

    #[Test]
    public function rien_n_est_supprime_si_le_central_n_envoie_pas_de_liste()
    {
        $vide = Category::factory()->create(['name' => 'Ancienne']);

        $this->pull([], []);

        $this->assertDatabaseHas('categories', ['id' => $vide->id]);
    }
}
