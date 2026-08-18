<?php

namespace Tests\Feature;

use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UserRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $targetUser;
    private string $adminToken;
    private PointOfSale $pos;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');
        Role::findOrCreate('caissier', 'api');
        Role::findOrCreate('gérant', 'api');

        $this->pos        = PointOfSale::factory()->create();
        $this->admin      = User::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $this->targetUser = User::factory()->create(['point_of_sale_id' => $this->pos->id]);

        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    private function asAdmin(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    // =========================================================================
    // GET /api/users/{user}/roles
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_roles_d_un_utilisateur()
    {
        $this->targetUser->assignRole('caissier');

        $response = $this->asAdmin()->getJson("/api/users/{$this->targetUser->id}/roles");

        $response->assertStatus(200)
                 ->assertJsonIsArray();

        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('caissier'));
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_roles()
    {
        $this->getJson("/api/users/{$this->targetUser->id}/roles")->assertStatus(401);
    }

    // =========================================================================
    // POST /api/users/{user}/roles
    // =========================================================================

    #[Test]
    public function admin_peut_assigner_un_role_a_un_utilisateur()
    {
        $response = $this->asAdmin()->postJson("/api/users/{$this->targetUser->id}/roles", [
            'role' => 'caissier',
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['message' => 'Role assigned']);

        $this->assertTrue($this->targetUser->fresh()->hasRole('caissier', 'api'));
    }

    #[Test]
    public function assignation_echoue_si_role_inexistant()
    {
        $this->asAdmin()
             ->postJson("/api/users/{$this->targetUser->id}/roles", [
                 'role' => 'role_qui_nexiste_pas',
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function assignation_echoue_si_champ_manquant()
    {
        $this->asAdmin()
             ->postJson("/api/users/{$this->targetUser->id}/roles", [])
             ->assertStatus(422);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_assigner_un_role()
    {
        $this->postJson("/api/users/{$this->targetUser->id}/roles", ['role' => 'caissier'])
             ->assertStatus(401);
    }

    // =========================================================================
    // DELETE /api/users/{user}/roles/{role}
    // =========================================================================

    #[Test]
    public function admin_peut_retirer_un_role_d_un_utilisateur()
    {
        $this->targetUser->assignRole('gérant');
        $role = Role::findByName('gérant', 'api');

        $this->asAdmin()
             ->deleteJson("/api/users/{$this->targetUser->id}/roles/{$role->id}")
             ->assertStatus(204);

        $this->assertFalse($this->targetUser->fresh()->hasRole('gérant', 'api'));
    }

    #[Test]
    public function impossible_de_retirer_le_dernier_role_admin_d_un_utilisateur()
    {
        $adminRole = Role::findByName('admin', 'api');

        $this->asAdmin()
             ->deleteJson("/api/users/{$this->admin->id}/roles/{$adminRole->id}")
             ->assertStatus(403)
             ->assertJsonPath('error', 'User must have at least one admin role');
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_retirer_un_role()
    {
        $this->targetUser->assignRole('caissier');
        $role = Role::findByName('caissier', 'api');

        $this->deleteJson("/api/users/{$this->targetUser->id}/roles/{$role->id}")
             ->assertStatus(401);
    }
}
