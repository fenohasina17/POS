<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Services\CatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Photos des produits reçues du Central.
 */
class CatalogSyncImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.central_url'   => 'https://central.test',
            'sync.api_key'       => 'cle-test',
            'sync.restaurant_id' => '101',
        ]);
        Storage::fake('public');
        PointOfSale::factory()->create(['name' => '101']);
    }

    private function row(?string $hash, bool $withKey = true): array
    {
        $row = ['ref' => 'PZ01', 'name' => 'Pizza Regina', 'category' => 'Pizza GM', 'price' => 30000];
        if ($withKey) {
            $row['image_hash'] = $hash;
        }

        return $row;
    }

    private array $catalogRow = [];
    private $imageResponse = null;

    // Un second Http::fake() ne remplace pas le premier : les réponses sont
    // lues ici à chaque requête, ce qui permet de les changer entre deux synchros.
    private function fake(array $row, $imageResponse = null): void
    {
        $this->catalogRow = $row;
        $this->imageResponse = $imageResponse;

        if ($this->faked ?? false) {
            return;
        }
        $this->faked = true;

        Http::fake([
            'central.test/api/catalog/images/*' => fn () => $this->imageResponse
                ?? Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg']),
            'central.test/api/catalog' => fn () => Http::response(['categories' => ['Pizza GM'], 'products' => [$this->catalogRow]]),
        ]);
    }

    private bool $faked = false;

    private function product(): Product
    {
        return Product::where('ref', 'PZ01')->first();
    }

    #[Test]
    public function la_photo_du_central_est_telechargee_et_enregistree()
    {
        $this->fake($this->row(str_repeat('a', 40)));
        app(CatalogSyncService::class)->pull();

        $product = $this->product();
        $this->assertSame('products/central/PZ01-aaaaaaaa.jpg', $product->image);
        $this->assertSame(str_repeat('a', 40), $product->central_image_hash);
        Storage::disk('public')->assertExists($product->image);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/catalog/images/PZ01') && $r->hasHeader('Authorization', 'Bearer cle-test'));
    }

    #[Test]
    public function une_photo_inchangee_n_est_pas_retelechargee()
    {
        $this->fake($this->row(str_repeat('a', 40)));
        app(CatalogSyncService::class)->pull();
        app(CatalogSyncService::class)->pull();

        Http::assertSentCount(3); // 2 catalogues + 1 seule photo
    }

    #[Test]
    public function une_nouvelle_photo_remplace_l_ancienne()
    {
        $this->fake($this->row(str_repeat('a', 40)));
        app(CatalogSyncService::class)->pull();
        $old = $this->product()->image;

        $this->fake($this->row(str_repeat('b', 40)), Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']));
        app(CatalogSyncService::class)->pull();

        $this->assertSame('products/central/PZ01-bbbbbbbb.png', $this->product()->image);
        Storage::disk('public')->assertMissing($old);
    }

    #[Test]
    public function une_photo_retiree_au_central_revient_a_l_image_par_defaut()
    {
        $this->fake($this->row(str_repeat('a', 40)));
        app(CatalogSyncService::class)->pull();
        $old = $this->product()->image;

        $this->fake($this->row(null));
        app(CatalogSyncService::class)->pull();

        $this->assertSame('default-product-image.jpg', $this->product()->image);
        $this->assertNull($this->product()->central_image_hash);
        Storage::disk('public')->assertMissing($old);
    }

    #[Test]
    public function un_echec_de_telechargement_est_retente_a_la_synchro_suivante()
    {
        $this->fake($this->row(str_repeat('a', 40)), Http::response('', 500));
        app(CatalogSyncService::class)->pull();

        $this->assertNull($this->product()->central_image_hash);
    }

    #[Test]
    public function une_photo_ajoutee_sur_la_caisse_est_gardee_si_le_central_n_en_gere_pas()
    {
        $category = Category::factory()->create(['name' => 'Pizza GM']);
        Product::factory()->create(['ref' => 'PZ01', 'category_id' => $category->id, 'image' => 'products/locale.jpg']);

        $this->fake($this->row(null, withKey: false));
        app(CatalogSyncService::class)->pull();

        $this->assertSame('products/locale.jpg', $this->product()->image);
    }
}
