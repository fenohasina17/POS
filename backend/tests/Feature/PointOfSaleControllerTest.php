<?php

namespace Tests\Feature;

use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PointOfSaleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;
    private PointOfSale $pos;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');

        $this->pos   = PointOfSale::factory()->create(['name' => 'POS Principal']);
        $this->admin = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    private function asAdmin(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    // =========================================================================
    // GET /api/point_of_sales
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_points_de_vente()
    {
        PointOfSale::factory()->create(['name' => 'POS 2']);

        $response = $this->asAdmin()->getJson('/api/point_of_sales');

        $response->assertStatus(200)
                 ->assertJsonIsArray()
                 ->assertJsonFragment(['name' => 'POS Principal'])
                 ->assertJsonFragment(['name' => 'POS 2']);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_pos()
    {
        $this->getJson('/api/point_of_sales')->assertStatus(401);
    }

    // =========================================================================
    // POST /api/point_of_sales
    // =========================================================================

    #[Test]
    public function admin_peut_creer_un_point_de_vente()
    {
        $response = $this->asAdmin()->postJson('/api/point_of_sales', ['name' => 'Nouveau POS']);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'Nouveau POS');

        $this->assertDatabaseHas('point_of_sales', ['name' => 'Nouveau POS']);
    }

    #[Test]
    public function creation_echoue_si_nom_manquant()
    {
        $this->asAdmin()
             ->postJson('/api/point_of_sales', [])
             ->assertStatus(422)
             ->assertJsonStructure(['errors' => ['name']]);
    }

    #[Test]
    public function creation_echoue_si_nom_deja_utilise()
    {
        $this->asAdmin()
             ->postJson('/api/point_of_sales', ['name' => 'POS Principal'])
             ->assertStatus(422);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_creer_un_pos()
    {
        $this->postJson('/api/point_of_sales', ['name' => 'Test'])
             ->assertStatus(401);
    }

    // =========================================================================
    // GET /api/point_of_sales/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_voir_un_point_de_vente()
    {
        $response = $this->asAdmin()->getJson("/api/point_of_sales/{$this->pos->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('name', 'POS Principal');
    }

    #[Test]
    public function retourne_404_pour_pos_inexistant()
    {
        $this->asAdmin()
             ->getJson('/api/point_of_sales/99999')
             ->assertStatus(404);
    }

    // =========================================================================
    // PUT /api/point_of_sales/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_modifier_un_point_de_vente()
    {
        $this->asAdmin()
             ->putJson("/api/point_of_sales/{$this->pos->id}", ['name' => 'POS Modifié'])
             ->assertStatus(200)
             ->assertJsonPath('name', 'POS Modifié');

        $this->assertDatabaseHas('point_of_sales', ['id' => $this->pos->id, 'name' => 'POS Modifié']);
    }

    #[Test]
    public function modification_retourne_404_pour_pos_inexistant()
    {
        $this->asAdmin()
             ->putJson('/api/point_of_sales/99999', ['name' => 'X'])
             ->assertStatus(404);
    }

    // =========================================================================
    // DELETE /api/point_of_sales/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_supprimer_un_point_de_vente()
    {
        $pos = PointOfSale::factory()->create();

        $this->asAdmin()
             ->deleteJson("/api/point_of_sales/{$pos->id}")
             ->assertStatus(200)
             ->assertJsonFragment(['message' => 'Point de vente supprimé']);

        $this->assertDatabaseMissing('point_of_sales', ['id' => $pos->id]);
    }

    #[Test]
    public function suppression_retourne_404_pour_pos_inexistant()
    {
        $this->asAdmin()
             ->deleteJson('/api/point_of_sales/99999')
             ->assertStatus(404);
    }

    // =========================================================================
    // POST/DELETE /api/point-of-sales/{pos}/users/{user}
    // =========================================================================

    #[Test]
    public function admin_peut_attacher_un_utilisateur_a_un_pos()
    {
        $user = User::factory()->create(['point_of_sale_id' => $this->pos->id]);

        $response = $this->asAdmin()
             ->postJson("/api/point-of-sales/{$this->pos->id}/users/{$user->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Utilisateur associé au point de vente avec succès.']);

        $this->assertDatabaseHas('point_of_sale_user', [
            'point_of_sale_id' => $this->pos->id,
            'user_id'          => $user->id,
        ]);
    }

    #[Test]
    public function admin_peut_detacher_un_utilisateur_d_un_pos()
    {
        $user = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->pos->assignedUsers()->syncWithoutDetaching([$user->id]);

        $response = $this->asAdmin()
             ->deleteJson("/api/point-of-sales/{$this->pos->id}/users/{$user->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Utilisateur retiré du point de vente avec succès.']);

        $this->assertDatabaseMissing('point_of_sale_user', [
            'point_of_sale_id' => $this->pos->id,
            'user_id'          => $user->id,
        ]);
    }
}
