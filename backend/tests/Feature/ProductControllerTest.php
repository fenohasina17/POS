<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Pricing;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $caissier;
    private string $adminToken;
    private string $caissierToken;
    private PointOfSale $pos;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');
        Role::findOrCreate('caissier', 'api');

        $this->pos      = PointOfSale::factory()->create();
        $this->category = Category::factory()->create();

        $this->admin = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->caissier = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->caissier->assignRole('caissier');
        $this->caissier->pointsOfSale()->attach($this->pos->id);
        $this->caissierToken = $this->caissier->createToken('test')->plainTextToken;
    }

    // Admin avec X-Active-POS-ID pour que le controller ait un targetPosId
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

    private function createProductForPos(): Product
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);
        $this->pos->products()->syncWithoutDetaching([$product->id]);
        Pricing::create([
            'product_id'       => $product->id,
            'point_of_sale_id' => $this->pos->id,
            'price'            => 1000,
        ]);
        return $product;
    }

    // =========================================================================
    // GET /api/products
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_produits()
    {
        $this->createProductForPos();

        $this->asAdmin()
             ->getJson('/api/products')
             ->assertStatus(200);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_produits()
    {
        $this->getJson('/api/products')->assertStatus(401);
    }

    #[Test]
    public function caissier_sans_pos_actif_obtient_403()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->caissierToken)
             ->getJson('/api/products')
             ->assertStatus(403);
    }

    #[Test]
    public function caissier_avec_pos_actif_peut_lister_ses_produits()
    {
        $this->createProductForPos();

        $this->asCaissier()
             ->getJson('/api/products')
             ->assertStatus(200);
    }

    // =========================================================================
    // POST /api/products
    // =========================================================================

    #[Test]
    public function admin_peut_creer_un_produit()
    {
        $response = $this->asAdmin()->postJson('/api/products', [
            'name'        => 'Pizza Margherita',
            'ref'         => 'PIZ001',
            'price'       => 12000,
            'status'      => true,
            'category_id' => $this->category->id,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('product.name', 'Pizza Margherita');

        $this->assertDatabaseHas('products', ['name' => 'Pizza Margherita']);
    }

    #[Test]
    public function creation_echoue_si_champs_requis_manquants()
    {
        $this->asAdmin()
             ->postJson('/api/products', [])
             ->assertStatus(422);
    }

    #[Test]
    public function creation_echoue_si_categorie_inexistante()
    {
        $this->asAdmin()->postJson('/api/products', [
            'name'        => 'Produit Test',
            'ref'         => 'TST001',
            'price'       => 5000,
            'status'      => true,
            'category_id' => 99999,
        ])->assertStatus(422);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_creer_un_produit()
    {
        $this->postJson('/api/products', [
            'name'        => 'Test',
            'ref'         => 'T01',
            'price'       => 1000,
            'status'      => true,
            'category_id' => $this->category->id,
        ])->assertStatus(401);
    }

    // =========================================================================
    // GET /api/products/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_voir_un_produit()
    {
        $product = $this->createProductForPos();

        $this->asAdmin()
             ->getJson("/api/products/{$product->id}")
             ->assertStatus(200)
             ->assertJsonPath('name', $product->name);
    }

    #[Test]
    public function retourne_404_si_produit_non_associe_au_pos()
    {
        $autrePos     = PointOfSale::factory()->create();
        $autreProduct = Product::factory()->create(['category_id' => $this->category->id]);
        $autrePos->products()->syncWithoutDetaching([$autreProduct->id]);

        // Admin avec POS courant — produit n'est pas dans ce POS
        $this->asAdmin()
             ->getJson("/api/products/{$autreProduct->id}")
             ->assertStatus(404);
    }

    // =========================================================================
    // PUT /api/products/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_modifier_un_produit()
    {
        $product = $this->createProductForPos();

        $response = $this->asAdmin()->putJson("/api/products/{$product->id}", [
            'name'  => 'Pizza Regina',
            'price' => 15000,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('product.name', 'Pizza Regina');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Pizza Regina']);
    }

    #[Test]
    public function modification_met_a_jour_le_prix_dans_pricing()
    {
        $product = $this->createProductForPos();

        $this->asAdmin()->putJson("/api/products/{$product->id}", ['price' => 20000]);

        $this->assertDatabaseHas('pricing', [
            'product_id'       => $product->id,
            'point_of_sale_id' => $this->pos->id,
            'price'            => 20000,
        ]);
    }

    // =========================================================================
    // DELETE /api/products/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_supprimer_un_produit_de_son_pos()
    {
        $product = $this->createProductForPos();

        $this->asAdmin()
             ->deleteJson("/api/products/{$product->id}")
             ->assertStatus(200);

        // Produit détaché du POS ; si plus aucun POS, supprimé de la DB
        $this->assertDatabaseMissing('point_of_sale_product', [
            'product_id'       => $product->id,
            'point_of_sale_id' => $this->pos->id,
        ]);
    }

    #[Test]
    public function suppression_echoue_si_produit_non_associe_au_pos()
    {
        $autrePos     = PointOfSale::factory()->create();
        $autreProduct = Product::factory()->create(['category_id' => $this->category->id]);
        $autrePos->products()->syncWithoutDetaching([$autreProduct->id]);

        $this->asAdmin()
             ->deleteJson("/api/products/{$autreProduct->id}")
             ->assertStatus(403);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_supprimer()
    {
        $product = $this->createProductForPos();
        $this->deleteJson("/api/products/{$product->id}")->assertStatus(401);
    }
}
