<?php

namespace Tests\Feature;

use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RolePermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;
    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');
        $this->role = Role::findOrCreate('gérant', 'api');

        $pos         = PointOfSale::factory()->create();
        $this->admin = User::factory()->create(['point_of_sale_id' => $pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    private function asAdmin(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    // =========================================================================
    // POST /api/roles/{role}/permissions
    // =========================================================================

    #[Test]
    public function admin_peut_assigner_une_permission_a_un_role()
    {
        Permission::findOrCreate('view.reports', 'api');

        $response = $this->asAdmin()->postJson("/api/roles/{$this->role->id}/permissions", [
            'permission' => 'view.reports',
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Permission assigned']);

        $this->assertTrue($this->role->fresh()->hasPermissionTo('view.reports', 'api'));
    }

    #[Test]
    public function assignation_echoue_si_permission_inexistante()
    {
        $this->asAdmin()
             ->postJson("/api/roles/{$this->role->id}/permissions", [
                 'permission' => 'permission.inexistante',
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function assignation_echoue_si_champ_manquant()
    {
        $this->asAdmin()
             ->postJson("/api/roles/{$this->role->id}/permissions", [])
             ->assertStatus(422);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_assigner_une_permission()
    {
        Permission::findOrCreate('view.reports', 'api');

        $this->postJson("/api/roles/{$this->role->id}/permissions", [
            'permission' => 'view.reports',
        ])->assertStatus(401);
    }

    // =========================================================================
    // DELETE /api/roles/{role}/permissions/{permission}
    // =========================================================================

    #[Test]
    public function admin_peut_revoquer_une_permission_d_un_role()
    {
        $permission = Permission::findOrCreate('export.sales', 'api');
        $this->role->givePermissionTo($permission);

        $this->asAdmin()
             ->deleteJson("/api/roles/{$this->role->id}/permissions/{$permission->id}")
             ->assertStatus(204);

        $this->assertFalse($this->role->fresh()->hasPermissionTo('export.sales', 'api'));
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_revoquer_une_permission()
    {
        $permission = Permission::findOrCreate('export.sales', 'api');
        $this->role->givePermissionTo($permission);

        $this->deleteJson("/api/roles/{$this->role->id}/permissions/{$permission->id}")
             ->assertStatus(401);
    }
}
