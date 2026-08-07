<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\CashTransaction;
use App\Models\User;
use App\Models\PointOfSale;
use App\Models\CashRegister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class CashTransactionTest extends TestCase
{
    use RefreshDatabase;

    private $pos;
    private $register;
    private $registerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Créer les permissions exactement comme la policy les attend
        Permission::firstOrCreate(['name' => 'create.transactions', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'delete.transactions', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'update.transactions', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'view.transactions', 'guard_name' => 'api']);
        
        // Créer le rôle admin avec toutes les permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $adminRole->givePermissionTo([
            'create.transactions',
            'delete.transactions',
            'update.transactions',
            'view.transactions'
        ]);
        
        // Créer un point de vente
        $this->pos = PointOfSale::create([
            'name' => 'POS Test',
            'address' => '123 Rue Test',
            'city' => 'Antananarivo',
            'phone' => '0341234567',
            'is_active' => true
        ]);
        
        // Créer une caisse
        $this->register = CashRegister::create([
            'name' => 'Caisse Test',
            'point_of_sale_id' => $this->pos->id,
            'ip_address' => '192.168.1.100',
            'is_active' => true
        ]);
        
        $this->registerId = $this->register->id;
    }

    private function authenticate(string $permissionName = 'view.transactions')
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'point_of_sale_id' => $this->pos->id 
        ]);

        // Donner la permission spécifique à l'utilisateur
        $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'api']);
        $user->givePermissionTo($permission);
        
        // Donner aussi view.transactions pour pouvoir voir
        if ($permissionName !== 'view.transactions') {
            $viewPermission = Permission::firstOrCreate(['name' => 'view.transactions', 'guard_name' => 'api']);
            $user->givePermissionTo($viewPermission);
        }

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);

        return [$user, $response->json('token') ?? $response->json('access_token')];
    }

    #[Test]
    public function it_updates_session_amount_when_sale_is_created()
    {
        [$user, $token] = $this->authenticate('create.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => false
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/cash-transactions', [
                 'session_id' => $session->id, 
                 'type' => 'sale', 
                 'amount' => 500
             ])->assertStatus(201);

        $this->assertEquals(1500, $session->refresh()->expected_cash_amount);
    }

    #[Test]
    public function it_updates_session_amount_when_deposit_is_created()
    {
        [$user, $token] = $this->authenticate('create.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => false
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/cash-transactions', [
                 'session_id' => $session->id, 
                 'type' => 'sale', 
                 'amount' => 300,
                 'description' => 'Dépôt espèce'
             ])->assertStatus(201);

        $this->assertEquals(1300, $session->refresh()->expected_cash_amount);
    }

    #[Test]
    public function it_updates_session_amount_when_withdrawal_is_created()
    {
        [$user, $token] = $this->authenticate('create.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => false
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/cash-transactions', [
                 'session_id' => $session->id, 
                 'type' => 'refund', 
                 'amount' => 200,
                 'description' => 'Retrait espèce'
             ])->assertStatus(201);

        $this->assertEquals(800, $session->refresh()->expected_cash_amount);
    }

    #[Test]
    public function it_adjusts_session_amount_correctly_on_update()
    {
        [$user, $token] = $this->authenticate('create.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => false
        ]);

        $transaction = CashTransaction::create([
            'session_id' => $session->id, 
            'type' => 'sale', 
            'amount' => 500,
            'created_by' => $user->id
        ]);

        $session->increment('expected_cash_amount', 500);
        $this->assertEquals(1500, $session->expected_cash_amount);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->putJson("/api/cash-transactions/{$transaction->id}", [
                 'amount' => 100 
             ])->assertStatus(200);

        $this->assertEquals(1100, $session->refresh()->expected_cash_amount);
    }
#[Test]

public function it_cannot_delete_transaction()
{
    [$user, $token] = $this->authenticate('delete.transactions');
    
    $session = CashRegisterSession::create([
        'cash_register_id' => $this->registerId,
        'user_id' => $user->id,
        'expected_cash_amount' => 1000,
        'starting_amount' => 1000,
        'is_closed' => false,
        'opened_at' => now(),
        'start_ticket_number' => 1,
        'total_sales' => 0
    ]);
    
    $transaction = CashTransaction::create([
        'session_id' => $session->id,
        'type' => 'sale', 
        'amount' => 200,
        'created_by' => $user->id
    ]);

    $this->withHeader('Authorization', 'Bearer ' . $token)
         ->deleteJson("/api/cash-transactions/{$transaction->id}")
         ->assertStatus(403);
}
    #[Test]
    public function it_prevents_creating_transaction_on_closed_session()
    {
        [$user, $token] = $this->authenticate('create.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => true,
            'closed_at' => now()
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/cash-transactions', [
                 'session_id' => $session->id, 
                 'type' => 'sale', 
                 'amount' => 500
             ])->assertStatus(403);
    }

    #[Test]
    public function it_prevents_updating_transaction_on_closed_session()
    {
        [$user, $token] = $this->authenticate('create.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => false
        ]);

        $transaction = CashTransaction::create([
            'session_id' => $session->id, 
            'type' => 'sale', 
            'amount' => 500,
            'created_by' => $user->id
        ]);

        $session->update(['is_closed' => true, 'closed_at' => now()]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->putJson("/api/cash-transactions/{$transaction->id}", [
                 'amount' => 100 
             ])->assertStatus(403);
    }

    #[Test]
    public function it_prevents_deleting_transaction_on_closed_session()
    {
        [$user, $token] = $this->authenticate('delete.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => false
        ]);

        $transaction = CashTransaction::create([
            'session_id' => $session->id, 
            'type' => 'sale', 
            'amount' => 500,
            'created_by' => $user->id
        ]);

        $session->update(['is_closed' => true, 'closed_at' => now()]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->deleteJson("/api/cash-transactions/{$transaction->id}")
             ->assertStatus(403);
    }

    #[Test]
    public function it_prevents_user_without_permission_from_creating_transaction()
    {
        // Créer un utilisateur sans permission create.transactions
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'point_of_sale_id' => $this->pos->id 
        ]);
        
        // Donner seulement view.transactions
        $viewPermission = Permission::firstOrCreate(['name' => 'view.transactions', 'guard_name' => 'api']);
        $user->givePermissionTo($viewPermission);
        
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);
        
        $token = $response->json('token') ?? $response->json('access_token');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId,
            'user_id' => $user->id,
            'expected_cash_amount' => 1000,
            'is_closed' => false
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/cash-transactions', [
                 'session_id' => $session->id,
                 'type' => 'sale',
                 'amount' => 100
             ])->assertStatus(403);
    }

    #[Test]
    public function it_prevents_user_without_permission_from_deleting_transaction()
    {
        // Créer un utilisateur avec create.transactions mais pas delete.transactions
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'point_of_sale_id' => $this->pos->id 
        ]);
        
        $createPermission = Permission::firstOrCreate(['name' => 'create.transactions', 'guard_name' => 'api']);
        $viewPermission = Permission::firstOrCreate(['name' => 'view.transactions', 'guard_name' => 'api']);
        $user->givePermissionTo([$createPermission, $viewPermission]);
        
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);
        
        $token = $response->json('token') ?? $response->json('access_token');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId,
            'user_id' => $user->id,
            'expected_cash_amount' => 1000,
            'is_closed' => false
        ]);

        // Créer une transaction avec un autre utilisateur (admin)
        $transaction = CashTransaction::create([
            'session_id' => $session->id, 
            'type' => 'sale', 
            'amount' => 200,
            'created_by' => $user->id
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->deleteJson("/api/cash-transactions/{$transaction->id}")
             ->assertStatus(403);
    }

    #[Test]
    public function it_validates_required_fields()
    {
        [$user, $token] = $this->authenticate('create.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => false
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/cash-transactions', [
                 'session_id' => $session->id
             ])->assertStatus(422);
    }

    #[Test]
    public function it_validates_amount_is_positive()
    {
        [$user, $token] = $this->authenticate('create.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => false
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/cash-transactions', [
                 'session_id' => $session->id, 
                 'type' => 'sale', 
                 'amount' => -100
             ])->assertStatus(422);
    }

    #[Test]
    public function it_validates_transaction_type()
    {
        [$user, $token] = $this->authenticate('create.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => false
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/cash-transactions', [
                 'session_id' => $session->id, 
                 'type' => 'invalid_type', 
                 'amount' => 100
             ])->assertStatus(422);
    }

    #[Test]
    public function it_returns_transaction_details()
    {
        [$user, $token] = $this->authenticate('create.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => false
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/cash-transactions', [
                 'session_id' => $session->id, 
                 'type' => 'sale', 
                 'amount' => 500,
                 'description' => 'Vente test',
                 'reference' => 'REF123'
             ])->assertStatus(201);

        $response->assertJsonStructure([
            'id', 'session_id', 'type', 'amount', 'created_at'
        ]);
        
        $this->assertEquals('sale', $response->json('type'));
        $this->assertEquals(500, $response->json('amount'));
    }

    #[Test]
    public function it_lists_transactions_for_session()
    {
        [$user, $token] = $this->authenticate('view.transactions');
        
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->registerId, 
            'user_id' => $user->id,
            'expected_cash_amount' => 1000, 
            'is_closed' => false
        ]);

        CashTransaction::create([
            'session_id' => $session->id, 
            'type' => 'sale', 
            'amount' => 500,
            'created_by' => $user->id
        ]);
        
        CashTransaction::create([
            'session_id' => $session->id, 
            'type' => 'refund', 
            'amount' => 200,
            'created_by' => $user->id
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
             ->getJson("/api/cash-transactions")
             ->assertStatus(200);

        $this->assertGreaterThanOrEqual(2, count($response->json()));
    }
}