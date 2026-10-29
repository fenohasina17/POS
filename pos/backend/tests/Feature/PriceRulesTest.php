<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PointOfSale;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogSyncService;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Prix fixe (imposé par le Central) / prix modifiable (par le gérant),
 * selon la catégorie.
 */
class PriceRulesTest extends TestCase
{
    use RefreshDatabase;

    private PointOfSale $pos;
    private User $gerant;
    private User $caissier;
    private Category $fixe;
    private Category $modifiable;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.central_url'   => 'https://central.test',
            'sync.api_key'       => 'cle-test',
            'sync.terminal_id'   => 'pos-101-1',
            'sync.restaurant_id' => '101',
        ]);

        foreach (array_unique(array_merge(RolePermissions::GERANT, RolePermissions::CAISSIER)) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api']);
        }
        Role::findOrCreate('admin', 'api');
        Role::findOrCreate('gérant', 'api')->givePermissionTo(RolePermissions::GERANT);
        Role::findOrCreate('caissier', 'api')->givePermissionTo(RolePermissions::CAISSIER);

        $this->pos = PointOfSale::factory()->create(['name' => '101']);

        $this->gerant = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->gerant->assignRole('gérant');
        $this->gerant->pointsOfSale()->attach($this->pos->id);

        $this->caissier = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->caissier->assignRole('caissier');
        $this->caissier->pointsOfSale()->attach($this->pos->id);

        $this->fixe       = Category::factory()->create(['name' => 'Pizza GM', 'price_editable' => false]);
        $this->modifiable = Category::factory()->create(['name' => 'Plat malgache', 'price_editable' => true]);
    }

    private function as(User $user): self
    {
        return $this->withHeaders([
            'Authorization'   => 'Bearer ' . $user->createToken('test')->plainTextToken,
            'X-Active-POS-ID' => (string) $this->pos->id,
        ]);
    }

    private function product(Category $category, float $price, string $ref): Product
    {
        $product = Product::factory()->create(['category_id' => $category->id, 'ref' => $ref]);
        $this->pos->products()->attach($product->id);
        Pricing::create([
            'product_id'       => $product->id,
            'point_of_sale_id' => $this->pos->id,
            'price'            => $price,
            'central_price'    => $price,
        ]);

        return $product;
    }

    private function pricing(Product $product): Pricing
    {
        return Pricing::where('product_id', $product->id)->where('point_of_sale_id', $this->pos->id)->first();
    }

    private function fakeCatalog(array $products): void
    {
        Http::fake([
            'central.test/api/catalog' => Http::response(['categories' => [], 'products' => $products]),
            'central.test/*'           => Http::response(['received' => 0]),
        ]);
    }

    #[Test]
    public function le_gerant_modifie_le_prix_d_une_categorie_modifiable()
    {
        $product = $this->product($this->modifiable, 20000, 'PL01');

        $this->as($this->gerant)->putJson("/api/products/{$product->id}", ['price' => 22000])
            ->assertOk();

        $pricing = $this->pricing($product);
        $this->assertEquals(22000, (float) $pricing->price);
        $this->assertTrue($pricing->is_local_override);
        $this->assertEquals(20000, (float) $pricing->central_price);
    }

    #[Test]
    public function le_gerant_ne_peut_pas_modifier_un_prix_fixe()
    {
        $product = $this->product($this->fixe, 30000, 'PZ01');

        $this->as($this->gerant)->putJson("/api/products/{$product->id}", ['price' => 25000])
            ->assertForbidden();

        $this->assertEquals(30000, (float) $this->pricing($product)->price);
    }

    #[Test]
    public function l_admin_enregistre_un_produit_a_prix_fixe_sans_changer_le_prix()
    {
        $admin = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $admin->assignRole('admin');
        $product = $this->product($this->fixe, 30000, 'PZ04');

        $this->as($admin)->putJson("/api/products/{$product->id}", ['name' => 'Pizza renommée', 'price' => 30000])
            ->assertOk();
        $this->as($admin)->putJson("/api/products/{$product->id}", ['price' => 31000])
            ->assertForbidden();

        $this->assertFalse($this->pricing($product)->is_local_override);
        $this->assertEquals(30000, (float) $this->pricing($product)->price);
    }

    #[Test]
    public function le_gerant_ne_peut_toujours_pas_modifier_le_nom()
    {
        $product = $this->product($this->modifiable, 20000, 'PL02');

        $this->as($this->gerant)->putJson("/api/products/{$product->id}", ['name' => 'Autre', 'price' => 1])
            ->assertForbidden();
    }

    #[Test]
    public function le_caissier_ne_peut_pas_modifier_un_prix()
    {
        $product = $this->product($this->modifiable, 20000, 'PL03');
        $pricing = $this->pricing($product);

        $this->as($this->caissier)->putJson("/api/products/{$product->id}", ['price' => 1])->assertForbidden();
        $this->as($this->caissier)->putJson("/api/pricings/{$pricing->id}", ['price' => 1])->assertForbidden();
        $this->as($this->caissier)->postJson('/api/pricings', ['product_id' => $product->id, 'price' => 1])->assertForbidden();
        $this->as($this->caissier)->deleteJson("/api/pricings/{$pricing->id}")->assertForbidden();

        $this->assertEquals(20000, (float) $this->pricing($product)->price);
    }

    #[Test]
    public function le_gerant_revient_au_prix_du_central()
    {
        $product = $this->product($this->modifiable, 20000, 'PL04');
        $this->pricing($product)->update(['price' => 18000, 'is_local_override' => true]);

        $this->as($this->gerant)->postJson("/api/products/{$product->id}/reset-price")->assertOk();

        $pricing = $this->pricing($product);
        $this->assertEquals(20000, (float) $pricing->price);
        $this->assertFalse($pricing->is_local_override);
    }

    #[Test]
    public function la_liste_des_produits_indique_si_le_prix_est_verrouille()
    {
        $this->product($this->fixe, 30000, 'PZ02');
        $this->product($this->modifiable, 20000, 'PL05');

        $locked = collect($this->as($this->gerant)->getJson('/api/products')->assertOk()->json())
            ->pluck('price_locked', 'ref');

        $this->assertTrue($locked['PZ02']);
        $this->assertFalse($locked['PL05']);
    }

    #[Test]
    public function sans_central_aucun_prix_n_est_verrouille()
    {
        config(['sync.central_url' => '']);
        $product = $this->product($this->fixe, 30000, 'PZ03');

        $this->assertFalse($product->fresh()->isPriceLocked());
    }

    #[Test]
    public function la_synchro_garde_le_prix_du_gerant_dans_une_categorie_modifiable()
    {
        $product = $this->product($this->modifiable, 20000, 'PL06');
        $this->pricing($product)->update(['price' => 22000, 'is_local_override' => true]);

        $this->fakeCatalog([
            ['ref' => 'PL06', 'name' => $product->name, 'category' => 'Plat malgache', 'price' => 21000, 'price_editable' => true],
        ]);
        app(CatalogSyncService::class)->pull();

        $pricing = $this->pricing($product);
        $this->assertEquals(22000, (float) $pricing->price);
        $this->assertEquals(21000, (float) $pricing->central_price);
        $this->assertTrue($pricing->is_local_override);
    }

    #[Test]
    public function la_synchro_impose_le_prix_quand_la_categorie_est_refermee()
    {
        $product = $this->product($this->modifiable, 20000, 'PL07');
        $this->pricing($product)->update(['price' => 22000, 'is_local_override' => true]);

        $this->fakeCatalog([
            ['ref' => 'PL07', 'name' => $product->name, 'category' => 'Plat malgache', 'price' => 21000, 'price_editable' => false],
        ]);
        app(CatalogSyncService::class)->pull();

        $pricing = $this->pricing($product);
        $this->assertEquals(21000, (float) $pricing->price);
        $this->assertFalse($pricing->is_local_override);
        $this->assertFalse($this->modifiable->fresh()->price_editable);
    }

    #[Test]
    public function la_synchro_suit_le_central_tant_que_le_gerant_n_a_rien_change()
    {
        $product = $this->product($this->modifiable, 20000, 'PL08');

        $this->fakeCatalog([
            ['ref' => 'PL08', 'name' => $product->name, 'category' => 'Plat malgache', 'price' => 21000, 'price_editable' => true],
        ]);
        $result = app(CatalogSyncService::class)->pull();

        $this->assertEquals(21000, (float) $this->pricing($product)->price);
        $this->assertSame(['PL08'], $result['updated']);
    }

    #[Test]
    public function un_central_ancien_sans_regle_garde_les_prix_imposes()
    {
        $product = $this->product($this->modifiable, 20000, 'PL09');
        $this->pricing($product)->update(['price' => 22000, 'is_local_override' => true]);

        $this->fakeCatalog([
            ['ref' => 'PL09', 'name' => $product->name, 'category' => 'Plat malgache', 'price' => 20000],
        ]);
        app(CatalogSyncService::class)->pull();

        $this->assertEquals(20000, (float) $this->pricing($product)->price);
    }

    #[Test]
    public function les_prix_sont_envoyes_au_central()
    {
        $product = $this->product($this->modifiable, 20000, 'PL10');
        $this->pricing($product)->update(['price' => 22000, 'is_local_override' => true]);
        $this->product($this->fixe, 30000, 'PZ10');
        $this->fakeCatalog([]);

        $this->assertTrue(app(CatalogSyncService::class)->pushPrices());

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://central.test/api/sync/prices') {
                return false;
            }
            $prices = collect($request->data()['prices'])->keyBy('ref');

            return $request->data()['restaurant_id'] === '101'
                && $prices['PL10']['price'] == 22000 && $prices['PL10']['is_local_override'] === true
                && $prices['PZ10']['price'] == 30000 && $prices['PZ10']['is_local_override'] === false;
        });
    }
}
