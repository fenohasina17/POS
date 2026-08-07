<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CategoryControllerTest extends TestCase
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

        $this->pos = PointOfSale::factory()->create();

        $this->admin = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->caissier = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->caissier->assignRole('caissier');
        $this->caissier->pointsOfSale()->attach($this->pos->id);
        $this->caissierToken = $this->caissier->createToken('test')->plainTextToken;
    }

    private function asAdmin(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    private function asCaissier(): self
    {
        return $this->withHeaders([
            'Authorization'   => 'Bearer ' . $this->caissierToken,
            'X-Active-POS-ID' => (string) $this->pos->id,
        ]);
    }

    // =========================================================================
    // GET /api/categories
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_categories()
    {
        Category::factory()->count(3)->create();

        $this->asAdmin()
             ->getJson('/api/categories')
             ->assertStatus(200)
             ->assertJsonPath('success', true)
             ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_categories()
    {
        $this->getJson('/api/categories')->assertStatus(401);
    }

    #[Test]
    public function caissier_peut_lister_les_categories_avec_pos_actif()
    {
        Category::factory()->count(2)->create();

        $this->asCaissier()
             ->getJson('/api/categories')
             ->assertStatus(200)
             ->assertJsonPath('success', true);
    }

    // =========================================================================
    // POST /api/categories
    // =========================================================================

    #[Test]
    public function admin_peut_creer_une_categorie()
    {
        $response = $this->asAdmin()->postJson('/api/categories', [
            'name'    => 'Boissons',
            'printer' => 'bar',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Boissons')
                 ->assertJsonPath('data.printer', 'bar');

        $this->assertDatabaseHas('categories', ['name' => 'Boissons']);
    }

    #[Test]
    public function creation_echoue_si_nom_manquant()
    {
        $this->asAdmin()
             ->postJson('/api/categories', [])
             ->assertStatus(422)
             ->assertJsonStructure(['errors' => ['name']]);
    }

    #[Test]
    public function creation_echoue_si_nom_deja_utilise()
    {
        Category::factory()->create(['name' => 'Desserts']);

        $this->asAdmin()
             ->postJson('/api/categories', ['name' => 'Desserts'])
             ->assertStatus(422)
             ->assertJsonStructure(['errors' => ['name']]);
    }

    #[Test]
    public function creation_echoue_si_printer_invalide()
    {
        $this->asAdmin()
             ->postJson('/api/categories', ['name' => 'Test', 'printer' => 'invalid'])
             ->assertStatus(422)
             ->assertJsonStructure(['errors' => ['printer']]);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_creer_une_categorie()
    {
        $this->postJson('/api/categories', ['name' => 'Test'])->assertStatus(401);
    }

    // =========================================================================
    // GET /api/categories/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_voir_une_categorie()
    {
        $category = Category::factory()->create(['name' => 'Entrées']);

        $this->asAdmin()
             ->getJson("/api/categories/{$category->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.name', 'Entrées');
    }

    #[Test]
    public function retourne_404_pour_categorie_inexistante()
    {
        $this->asAdmin()
             ->getJson('/api/categories/99999')
             ->assertStatus(404);
    }

    // =========================================================================
    // PUT /api/categories/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_modifier_une_categorie()
    {
        $category = Category::factory()->create(['name' => 'Ancienne']);

        $this->asAdmin()
             ->putJson("/api/categories/{$category->id}", ['name' => 'Nouvelle'])
             ->assertStatus(200)
             ->assertJsonPath('data.name', 'Nouvelle');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Nouvelle']);
    }

    #[Test]
    public function modification_echoue_si_nom_deja_pris_par_une_autre_categorie()
    {
        Category::factory()->create(['name' => 'Plats']);
        $category = Category::factory()->create(['name' => 'Desserts']);

        $this->asAdmin()
             ->putJson("/api/categories/{$category->id}", ['name' => 'Plats'])
             ->assertStatus(422);
    }

    #[Test]
    public function modification_peut_garder_le_meme_nom()
    {
        $category = Category::factory()->create(['name' => 'Plats']);

        $this->asAdmin()
             ->putJson("/api/categories/{$category->id}", ['name' => 'Plats', 'printer' => 'kitchen'])
             ->assertStatus(200);
    }

    // =========================================================================
    // DELETE /api/categories/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_supprimer_une_categorie()
    {
        $category = Category::factory()->create();

        $this->asAdmin()
             ->deleteJson("/api/categories/{$category->id}")
             ->assertStatus(200)
             ->assertJsonPath('message', 'Category deleted successfully');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    #[Test]
    public function suppression_retourne_404_pour_categorie_inexistante()
    {
        $this->asAdmin()
             ->deleteJson('/api/categories/99999')
             ->assertStatus(404);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_supprimer()
    {
        $category = Category::factory()->create();
        $this->deleteJson("/api/categories/{$category->id}")->assertStatus(401);
    }
}
