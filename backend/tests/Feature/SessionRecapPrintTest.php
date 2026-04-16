<?php
// tests/Feature/Printer/SessionRecapPrintTest.php

namespace Tests\Feature\Printer;

error_reporting(0);

use Tests\TestCase;
use App\Models\User;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Payment;
use App\Models\PointOfSale;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\Category;
use App\Models\OrderLine;
use App\Models\Table;
use App\Models\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SessionRecapPrintTest extends TestCase
{
    use RefreshDatabase;

    protected $pointOfSale;
    protected $cashRegister;
    protected $paymentCash;
    protected $paymentCard;
    protected $product1;
    protected $product2;
    protected $product3;
    protected $table;

    protected function setUp(): void
    {
        parent::setUp();

        // Rôles et permissions
        Role::create(['name' => 'caissier', 'guard_name' => 'api']);
        Role::create(['name' => 'gerant', 'guard_name' => 'api']);
        Role::create(['name' => 'admin', 'guard_name' => 'api']);
        Permission::create(['name' => 'print.invoice', 'guard_name' => 'api']);
        Permission::create(['name' => 'create.sales', 'guard_name' => 'api']);
        Permission::create(['name' => 'view.sales', 'guard_name' => 'api']);

        // Point de vente
        $this->pointOfSale = PointOfSale::create(['name' => 'Point de vente Test']);

        // Caisse
        $this->cashRegister = CashRegister::create([
            'name' => 'Caisse Principale',
            'point_of_sale_id' => $this->pointOfSale->id
        ]);

        // Table
        $this->table = Table::create([
            'table_number' => '01',
            'name' => 'Table 1',
            'capacity' => 4,
            'status' => 'available',
            'point_of_sale_id' => $this->pointOfSale->id,
            'location' => json_encode(['x' => 100, 'y' => 200])
        ]);


        // Méthodes de paiement
        $this->paymentCash = Payment::create(['name' => 'Espèces']);
        $this->paymentCard = Payment::create(['name' => 'Carte Bancaire']);

        // Catégorie
        $category = Category::create(['name' => 'Boissons']);
        
        // Création des produits
        $this->product1 = Product::create([
            'name' => 'Coca Cola',
            'ref' => 'COCA001',
            'category_id' => $category->id,
            'status' => true
        ]);
        
        $this->product2 = Product::create([
            'name' => 'Fanta',
            'ref' => 'FANTA001',
            'category_id' => $category->id,
            'status' => true
        ]);
        
        $this->product3 = Product::create([
            'name' => 'Sprite',
            'ref' => 'SPR001',
            'category_id' => $category->id,
            'status' => true
        ]);

        // Création des prix
        Pricing::create([
            'point_of_sale_id' => $this->pointOfSale->id,
            'product_id' => $this->product1->id,
            'price' => 2000
        ]);
        
        Pricing::create([
            'point_of_sale_id' => $this->pointOfSale->id,
            'product_id' => $this->product2->id,
            'price' => 1800
        ]);
        
        Pricing::create([
            'point_of_sale_id' => $this->pointOfSale->id,
            'product_id' => $this->product3->id,
            'price' => 1900
        ]);
    }

    private function createUserWithRole(string $role, array $permissions = [])
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'point_of_sale_id' => $this->pointOfSale->id
        ]);
        $user->assignRole($role);
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }
        return $user;
    }

    private function authenticateUser($user)
    {
        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);
        return $loginResponse->json('token') ?? $loginResponse->json('access_token');
    }

    private function createSession($user)
    {
        return CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $user->id,
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
            'starting_amount' => 100000,
            'actual_cash_amount' => 350000,
            'expected_cash_amount' => 340000,
            'difference_amount' => 10000,
            'total_sales' => 0,
            'is_closed' => true,
            'notes' => 'Session de test'
        ]);
    }

    private function getProductPrice($productId)
    {
        $pricing = Pricing::where('point_of_sale_id', $this->pointOfSale->id)
            ->where('product_id', $productId)
            ->first();
        return $pricing ? $pricing->price : 0;
    }

    private function createRealSales($session, $user)
    {
        echo "\n🛒 SIMULATION DE VENTES:\n";
        
        $price1 = $this->getProductPrice($this->product1->id);
        $price2 = $this->getProductPrice($this->product2->id);
        $price3 = $this->getProductPrice($this->product3->id);
        
        $items = [
            ['product' => $this->product1, 'quantity' => 3, 'price' => $price1],
            ['product' => $this->product2, 'quantity' => 2, 'price' => $price2],
            ['product' => $this->product3, 'quantity' => 4, 'price' => $price3],
        ];
        
        $totalSales = 0;
        $orderItems = [];

        echo "   ┌─────────────────────────────────────────────┐\n";
        echo "   │ DÉTAIL DE LA COMMANDE                       │\n";
        echo "   ├─────────────────────────────────────────────┤\n";
        
        foreach ($items as $item) {
            $total = $item['quantity'] * $item['price'];
            $totalSales += $total;
            echo "   │ " . str_pad($item['product']->name, 20) . " x{$item['quantity']}  = " . str_pad(number_format($total, 0, ',', ' ') . " Ar", 20, ' ', STR_PAD_LEFT) . " │\n";
            
            $orderItems[] = [
                'product_id' => $item['product']->id,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total' => $total
            ];
        }
        
        echo "   ├─────────────────────────────────────────────┤\n";
        echo "   │ TOTAL: " . str_pad(number_format($totalSales, 0, ',', ' ') . " Ar", 39, ' ', STR_PAD_LEFT) . " │\n";
        echo "   └─────────────────────────────────────────────┘\n";

        // Créer la vente
        $sale = Sale::create([
            'user_id' => $user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'table_id' => $this->table->id,
            'cash_register_session_id' => $session->id,
            'ticket_number' => 1,
            'total_amount' => $totalSales,
            'discount_percentage' => 0,
            'final_amount' => $totalSales,
            'amount_received' => $totalSales,
            'change_amount' => 0,
            'status' => 'completed',
            'payment_id' => $this->paymentCash->id,
            'created_at' => now()
        ]);

        // Ajouter les lignes de commande
        foreach ($orderItems as $item) {
            OrderLine::create([
                'sale_id' => $sale->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total' => $item['total']
            ]);
        }

        // Enregistrer le paiement
        SalePayment::create([
            'sale_id' => $sale->id,
            'payment_id' => $this->paymentCash->id,
            'amount' => $totalSales,
            'reference' => 'PAY-001'
        ]);

        // Mettre à jour le total de la session
        $session->update(['total_sales' => $totalSales]);

        echo "\n✅ Vente créée avec succès (Ticket #{$sale->ticket_number})\n";
        echo "   Montant total: " . number_format($totalSales, 0, ',', ' ') . " Ar\n";
        
        return $sale;
    }
}