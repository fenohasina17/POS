<?php

namespace Tests\Feature;

use App\Events\TableLockUpdated;
use App\Models\CashRegisterSession;
use App\Models\CashRegister;
use App\Models\PointOfSale;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TableControllerTest extends TestCase
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

        // Évite que TableLockUpdated (ShouldBroadcastNow) tente de se connecter à Reverb en test
        Event::fake([TableLockUpdated::class]);

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

    private function createTable(array $overrides = []): Table
    {
        return Table::factory()->create(array_merge([
            'point_of_sale_id' => $this->pos->id,
            'status'           => 'available',
        ], $overrides));
    }

    // =========================================================================
    // GET /api/tables
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_tables()
    {
        $this->createTable();
        $this->createTable();

        $this->asAdmin()
             ->getJson('/api/tables')
             ->assertStatus(200)
             ->assertJsonIsArray()
             ->assertJsonCount(2);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_tables()
    {
        $this->getJson('/api/tables')->assertStatus(401);
    }

    #[Test]
    public function caissier_avec_pos_actif_peut_lister_les_tables()
    {
        $this->createTable();

        $this->asCaissier()
             ->getJson('/api/tables')
             ->assertStatus(200)
             ->assertJsonIsArray();
    }

    #[Test]
    public function caissier_sans_pos_actif_obtient_403()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->caissierToken)
             ->getJson('/api/tables')
             ->assertStatus(403);
    }

    // =========================================================================
    // POST /api/tables
    // =========================================================================

    #[Test]
    public function admin_peut_creer_une_table()
    {
        $response = $this->asAdmin()->postJson('/api/tables', [
            'table_number' => 'T01',
            'capacity'     => 4,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('table_number', 'T01')
                 ->assertJsonPath('point_of_sale_id', $this->pos->id);

        $this->assertDatabaseHas('tables', ['table_number' => 'T01', 'point_of_sale_id' => $this->pos->id]);
    }

    #[Test]
    public function creation_echoue_si_champs_requis_manquants()
    {
        $this->asAdmin()
             ->postJson('/api/tables', [])
             ->assertStatus(422)
             ->assertJsonStructure(['error', 'details']);
    }

    #[Test]
    public function creation_echoue_si_numero_deja_utilise_dans_le_meme_pos()
    {
        $this->createTable(['table_number' => 'T01']);

        $this->asAdmin()
             ->postJson('/api/tables', ['table_number' => 'T01', 'capacity' => 4])
             ->assertStatus(422);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_creer_une_table()
    {
        $this->postJson('/api/tables', ['table_number' => 'T01', 'capacity' => 4])
             ->assertStatus(401);
    }

    // =========================================================================
    // GET /api/tables/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_voir_une_table()
    {
        $table = $this->createTable(['table_number' => 'T10']);

        $this->asAdmin()
             ->getJson("/api/tables/{$table->id}")
             ->assertStatus(200)
             ->assertJsonPath('table_number', 'T10');
    }

    #[Test]
    public function retourne_404_pour_table_inexistante()
    {
        $this->asAdmin()
             ->getJson('/api/tables/99999')
             ->assertStatus(404);
    }

    #[Test]
    public function retourne_404_si_table_appartient_a_un_autre_pos()
    {
        $autrePos   = PointOfSale::factory()->create();
        $autreTable = Table::factory()->create(['point_of_sale_id' => $autrePos->id]);

        $this->asAdmin()
             ->getJson("/api/tables/{$autreTable->id}")
             ->assertStatus(404);
    }

    // =========================================================================
    // PUT /api/tables/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_modifier_une_table()
    {
        $table = $this->createTable(['table_number' => 'T01', 'capacity' => 4]);

        $this->asAdmin()
             ->putJson("/api/tables/{$table->id}", [
                 'table_number' => 'T01',
                 'capacity'     => 6,
                 'status'       => 'available',
             ])
             ->assertStatus(200)
             ->assertJsonPath('capacity', 6);

        $this->assertDatabaseHas('tables', ['id' => $table->id, 'capacity' => 6]);
    }

    #[Test]
    public function modification_retourne_404_pour_table_inexistante()
    {
        $this->asAdmin()
             ->putJson('/api/tables/99999', [
                 'table_number' => 'T99',
                 'capacity'     => 4,
                 'status'       => 'available',
             ])
             ->assertStatus(404);
    }

    // =========================================================================
    // DELETE /api/tables/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_supprimer_une_table_sans_ventes_actives()
    {
        $table = $this->createTable();

        $this->asAdmin()
             ->deleteJson("/api/tables/{$table->id}")
             ->assertStatus(204);

        $this->assertDatabaseMissing('tables', ['id' => $table->id]);
    }

    #[Test]
    public function suppression_retourne_404_pour_table_inexistante()
    {
        $this->asAdmin()
             ->deleteJson('/api/tables/99999')
             ->assertStatus(404);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_supprimer_une_table()
    {
        $table = $this->createTable();
        $this->deleteJson("/api/tables/{$table->id}")->assertStatus(401);
    }

    // =========================================================================
    // PATCH /api/tables/{id}/status
    // =========================================================================

    #[Test]
    public function admin_peut_changer_le_statut_d_une_table()
    {
        $table = $this->createTable(['status' => 'available']);

        $this->asAdmin()
             ->patchJson("/api/tables/{$table->id}/status", ['status' => 'occupied'])
             ->assertStatus(200)
             ->assertJsonPath('status', 'occupied');
    }

    #[Test]
    public function statut_invalide_retourne_422()
    {
        $table = $this->createTable();

        $this->asAdmin()
             ->patchJson("/api/tables/{$table->id}/status", ['status' => 'invalid_status'])
             ->assertStatus(422);
    }

    // =========================================================================
    // GET /api/tables/statistics
    // =========================================================================

    #[Test]
    public function admin_peut_voir_les_statistiques_des_tables()
    {
        $this->createTable(['status' => 'available']);
        $this->createTable(['status' => 'occupied']);

        $response = $this->asAdmin()->getJson('/api/tables/statistics');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'total_tables',
                     'available_tables',
                     'occupied_tables',
                     'occupancy_rate',
                 ])
                 ->assertJsonPath('total_tables', 2)
                 ->assertJsonPath('available_tables', 1)
                 ->assertJsonPath('occupied_tables', 1);
    }

    // =========================================================================
    // POST /api/tables/{id}/lock + unlock
    // =========================================================================

    #[Test]
    public function admin_peut_verrouiller_une_table_avec_session_active()
    {
        $table        = $this->createTable(['status' => 'available']);
        $cashRegister = CashRegister::factory()->create(['point_of_sale_id' => $this->pos->id]);
        CashRegisterSession::factory()->create([
            'cash_register_id' => $cashRegister->id,
            'user_id'          => $this->admin->id,
            'is_closed'        => false,
        ]);

        $this->asAdmin()
             ->postJson("/api/tables/{$table->id}/lock")
             ->assertStatus(200)
             ->assertJsonPath('message', 'Table verrouillée');

        $this->assertDatabaseHas('tables', ['id' => $table->id, 'status' => 'occupied']);
    }

    #[Test]
    public function verrouillage_echoue_sans_session_active()
    {
        $table = $this->createTable();

        $this->asAdmin()
             ->postJson("/api/tables/{$table->id}/lock")
             ->assertStatus(422);
    }

    #[Test]
    public function admin_peut_deverrouiller_une_table()
    {
        $cashRegister = CashRegister::factory()->create(['point_of_sale_id' => $this->pos->id]);
        $session      = CashRegisterSession::factory()->create([
            'cash_register_id' => $cashRegister->id,
            'user_id'          => $this->admin->id,
            'is_closed'        => false,
        ]);
        $table = $this->createTable([
            'status'               => 'occupied',
            'locked_by_session_id' => $session->id,
            'locked_at'            => now(),
        ]);

        $this->asAdmin()
             ->postJson("/api/tables/{$table->id}/unlock")
             ->assertStatus(200)
             ->assertJsonPath('message', 'Table déverrouillée');

        $this->assertDatabaseHas('tables', [
            'id'                   => $table->id,
            'status'               => 'available',
            'locked_by_session_id' => null,
        ]);
    }
}
