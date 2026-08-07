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

class OrderLineControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;
    private PointOfSale $pos;
    private Sale $sale;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');

        $this->pos = PointOfSale::factory()->create();

        $this->admin = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $category      = Category::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->product = Product::factory()->create(['category_id' => $category->id]);
        $this->sale    = Sale::factory()->create([
            'user_id'          => $this->admin->id,
            'point_of_sale_id' => $this->pos->id,
        ]);
    }

    private function asAdmin(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    private function createOrderLine(array $overrides = []): OrderLine
    {
        return OrderLine::create(array_merge([
            'sale_id'    => $this->sale->id,
            'product_id' => $this->product->id,
            'quantity'   => 2,
            'price'      => 1000,
            'total'      => 2000,
        ], $overrides));
    }

    // =========================================================================
    // GET /api/orderlines
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_lignes_de_commande()
    {
        $this->createOrderLine();
        $this->createOrderLine(['quantity' => 3]);

        $response = $this->asAdmin()->getJson('/api/orderlines');

        $response->assertStatus(200)
                 ->assertJsonIsArray()
                 ->assertJsonCount(2);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_lignes()
    {
        $this->getJson('/api/orderlines')->assertStatus(401);
    }

    // =========================================================================
    // GET /api/orderlines/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_voir_une_ligne_de_commande()
    {
        $line = $this->createOrderLine(['quantity' => 5]);

        $this->asAdmin()
             ->getJson("/api/orderlines/{$line->id}")
             ->assertStatus(200)
             ->assertJsonPath('quantity', 5);
    }

    #[Test]
    public function retourne_404_pour_ligne_inexistante()
    {
        $this->asAdmin()
             ->getJson('/api/orderlines/99999')
             ->assertStatus(404);
    }

    // =========================================================================
    // POST /api/orderlines
    // =========================================================================

    #[Test]
    public function admin_peut_creer_une_ligne_de_commande()
    {
        $response = $this->asAdmin()->postJson('/api/orderlines', [
            'sale_id'    => $this->sale->id,
            'product_id' => $this->product->id,
            'quantity'   => 3,
            'price'      => 1500,
            'total'      => 4500,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('quantity', 3)
                 ->assertJsonPath('total', 4500);

        $this->assertDatabaseHas('order_lines', [
            'sale_id'    => $this->sale->id,
            'product_id' => $this->product->id,
            'quantity'   => 3,
        ]);
    }

    #[Test]
    public function creation_echoue_si_champs_requis_manquants()
    {
        $this->asAdmin()
             ->postJson('/api/orderlines', [])
             ->assertStatus(422);
    }

    #[Test]
    public function creation_echoue_si_sale_inexistante()
    {
        $this->asAdmin()
             ->postJson('/api/orderlines', [
                 'sale_id'    => 99999,
                 'product_id' => $this->product->id,
                 'quantity'   => 1,
                 'price'      => 1000,
                 'total'      => 1000,
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_creer_une_ligne()
    {
        $this->postJson('/api/orderlines', [
            'sale_id'    => $this->sale->id,
            'product_id' => $this->product->id,
            'quantity'   => 1,
            'price'      => 1000,
            'total'      => 1000,
        ])->assertStatus(401);
    }

    // =========================================================================
    // PUT /api/orderlines/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_modifier_une_ligne_de_commande()
    {
        $line = $this->createOrderLine(['quantity' => 2, 'total' => 2000]);

        $this->asAdmin()
             ->putJson("/api/orderlines/{$line->id}", [
                 'sale_id'    => $this->sale->id,
                 'product_id' => $this->product->id,
                 'quantity'   => 5,
                 'price'      => 1000,
                 'total'      => 5000,
             ])
             ->assertStatus(200)
             ->assertJsonPath('quantity', 5)
             ->assertJsonPath('total', 5000);
    }

    #[Test]
    public function modification_retourne_404_pour_ligne_inexistante()
    {
        $this->asAdmin()
             ->putJson('/api/orderlines/99999', [
                 'sale_id'    => $this->sale->id,
                 'product_id' => $this->product->id,
                 'quantity'   => 1,
                 'price'      => 1000,
                 'total'      => 1000,
             ])
             ->assertStatus(404);
    }

    // =========================================================================
    // DELETE /api/orderlines/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_supprimer_une_ligne_de_commande()
    {
        $line = $this->createOrderLine();

        $this->asAdmin()
             ->deleteJson("/api/orderlines/{$line->id}")
             ->assertStatus(200)
             ->assertJsonFragment(['message' => 'Order line deleted successfully']);

        $this->assertDatabaseMissing('order_lines', ['id' => $line->id]);
    }

    #[Test]
    public function suppression_retourne_404_pour_ligne_inexistante()
    {
        $this->asAdmin()
             ->deleteJson('/api/orderlines/99999')
             ->assertStatus(404);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_supprimer_une_ligne()
    {
        $line = $this->createOrderLine();
        $this->deleteJson("/api/orderlines/{$line->id}")->assertStatus(401);
    }
}
