<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Poste « fastfood » séparé de la cuisine : les catégories Fast food y ont
 * leurs propres bons de préparation.
 */
class FastfoodPrinterTest extends TestCase
{
    use RefreshDatabase;

    private function migration()
    {
        return require database_path('migrations/2026_11_27_000002_add_fastfood_printer_to_categories.php');
    }

    #[Test]
    public function la_base_accepte_le_poste_fastfood()
    {
        $category = Category::factory()->create(['printer' => 'fastfood']);

        $this->assertSame('fastfood', $category->fresh()->printer);
    }

    #[Test]
    public function la_migration_passe_les_categories_fast_food_de_la_cuisine_au_poste_fastfood()
    {
        $this->migration()->down();
        $avecPorc = Category::factory()->create(['name' => 'Fast food avec porc', 'printer' => 'kitchen']);
        $sansPorc = Category::factory()->create(['name' => 'Fast food sans porc', 'printer' => 'kitchen']);
        $pates = Category::factory()->create(['name' => 'Pâtes sans porc', 'printer' => 'kitchen']);
        $reglageLocal = Category::factory()->create(['name' => 'Fast food maison', 'printer' => 'bar']);

        $this->migration()->up();

        $this->assertSame('fastfood', $avecPorc->fresh()->printer);
        $this->assertSame('fastfood', $sansPorc->fresh()->printer);
        $this->assertSame('kitchen', $pates->fresh()->printer);
        $this->assertSame('bar', $reglageLocal->fresh()->printer, 'un réglage local autre que cuisine est conservé');
    }

    #[Test]
    public function l_api_accepte_fastfood_et_refuse_un_poste_inconnu()
    {
        Role::findOrCreate('admin', 'api');
        $admin = User::factory()->create(['point_of_sale_id' => PointOfSale::factory()->create()->id]);
        $admin->assignRole('admin');
        $api = $this->withHeader('Authorization', 'Bearer ' . $admin->createToken('test')->plainTextToken);

        $api->postJson('/api/categories', ['name' => 'Fast food test', 'printer' => 'fastfood'])
            ->assertStatus(201)
            ->assertJsonPath('data.printer', 'fastfood');

        $api->postJson('/api/categories', ['name' => 'Autre', 'printer' => 'friteuse'])
            ->assertStatus(422);
    }
}
