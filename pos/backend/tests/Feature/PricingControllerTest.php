<?php

namespace Tests\Feature;

use App\Models\Pricing;
use App\Models\Product;
use App\Models\PointOfSale;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PricingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $caissier;
    private string $adminToken;
    private string $caissierToken;
    private PointOfSale $pos;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');
        Role::findOrCreate('caissier', 'api');

        $this->pos = PointOfSale::factory()->create();

        $this->admin = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->caissier = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->caissier->assignRole('caissier');
        $this->caissier->pointsOfSale()->attach($this->pos->id);
        $this->caissierToken = $this->caissier->createToken('test')->plainTextToken;

        $category      = Category::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->product = Product::factory()->create(['category_id' => $category->id]);
    }

    private function asAdmin(): self
    {
        return $this->withHeaders([
            'Authorization'   => 'Bearer ' . $this->adminToken,
            'X-Active-POS-ID' => (string) $this->pos->id,
        ]);
    }

    private function asCaissier(): self
    {
        return $this->withHeaders([
            'Authorization'   => 'Bearer ' . $this->caissierToken,
            'X-Active-POS-ID' => (string) $this->pos->id,
        ]);
    }

    private function createPricing(array $overrides = []): Pricing
    {
        return Pricing::create(array_merge([
            'product_id'       => $this->product->id,
            'point_of_sale_id' => $this->pos->id,
            'price'            => 1000,
        ], $overrides));
    }

    // =========================================================================
    // GET /api/pricings
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_pricings()
    {
        $this->createPricing(['price' => 1500]);

        $response = $this->asAdmin()->getJson('/api/pricings');

        $response->assertStatus(200)
                 ->assertJsonIsArray()
                 ->assertJsonFragment(['price' => '1500']);
    }

    #[Test]
    public function caissier_peut_lister_les_pricings_de_son_pos()
    {
        $this->createPricing(['price' => 2000]);

        $autrePos     = PointOfSale::factory()->create();
        $autreProduct = Product::factory()->create(['category_id' => Category::factory()->create(['point_of_sale_id' => $autrePos->id])->id]);
        Pricing::create(['product_id' => $autreProduct->id, 'point_of_sale_id' => $autrePos->id, 'price' => 9999]);

        $response = $this->asCaissier()->getJson('/api/pricings');

        $response->assertStatus(200)->assertJsonIsArray();
        $posIds = collect($response->json())->pluck('point_of_sale_id')->unique()->values();
        $this->assertTrue($posIds->count() === 1 && $posIds->first() == $this->pos->id);
    }

    #[Test]
    public function caissier_sans_pos_actif_obtient_403()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->caissierToken)
             ->getJson('/api/pricings')
             ->assertStatus(403);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_pricings()
    {
        $this->getJson('/api/pricings')->assertStatus(401);
    }

    // =========================================================================
    // GET /api/pricings/{id}  (id = product_id dans le controller)
    // =========================================================================

    #[Test]
    public function admin_peut_voir_un_pricing_par_produit()
    {
        $this->createPricing(['price' => 3000]);

        $response = $this->asAdmin()->getJson("/api/pricings/{$this->product->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['price' => '3000']);
    }

    #[Test]
    public function retourne_404_si_pricing_inexistant_pour_ce_produit()
    {
        $autreProduct = Product::factory()->create(['category_id' => Category::factory()->create(['point_of_sale_id' => $this->pos->id])->id]);

        $this->asAdmin()
             ->getJson("/api/pricings/{$autreProduct->id}")
             ->assertStatus(404);
    }

    // =========================================================================
    // POST /api/pricings
    // =========================================================================

    #[Test]
    public function admin_peut_creer_un_pricing()
    {
        $response = $this->asAdmin()->postJson('/api/pricings', [
            'product_id' => $this->product->id,
            'price'      => 2500,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('pricings', [
            'product_id'       => $this->product->id,
            'point_of_sale_id' => $this->pos->id,
            'price'            => 2500,
        ]);
    }

    #[Test]
    public function creation_echoue_si_doublon_produit_pos()
    {
        $this->createPricing();

        $this->asAdmin()
             ->postJson('/api/pricings', [
                 'product_id' => $this->product->id,
                 'price'      => 5000,
             ])
             ->assertStatus(409);
    }

    #[Test]
    public function creation_echoue_si_champs_requis_manquants()
    {
        $this->asAdmin()
             ->postJson('/api/pricings', [])
             ->assertStatus(422);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_creer_un_pricing()
    {
        $this->postJson('/api/pricings', ['product_id' => $this->product->id, 'price' => 1000])
             ->assertStatus(401);
    }

    // =========================================================================
    // PUT /api/pricings/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_modifier_un_pricing()
    {
        $pricing = $this->createPricing(['price' => 1000]);

        $this->asAdmin()
             ->putJson("/api/pricings/{$pricing->id}", ['price' => 4500])
             ->assertStatus(200)
             ->assertJsonFragment(['price' => '4500']);

        $this->assertDatabaseHas('pricings', ['id' => $pricing->id, 'price' => 4500]);
    }

    #[Test]
    public function modification_retourne_404_pour_pricing_inexistant()
    {
        $this->asAdmin()
             ->putJson('/api/pricings/99999', ['price' => 1000])
             ->assertStatus(404);
    }

    // =========================================================================
    // DELETE /api/pricings/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_supprimer_un_pricing()
    {
        $pricing = $this->createPricing();

        $this->asAdmin()
             ->deleteJson("/api/pricings/{$pricing->id}")
             ->assertStatus(204);

        $this->assertDatabaseMissing('pricings', ['id' => $pricing->id]);
    }

    #[Test]
    public function suppression_retourne_404_pour_pricing_inexistant()
    {
        $this->asAdmin()
             ->deleteJson('/api/pricings/99999')
             ->assertStatus(404);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_supprimer_un_pricing()
    {
        $pricing = $this->createPricing();
        $this->deleteJson("/api/pricings/{$pricing->id}")->assertStatus(401);
    }
}
