<?php
// tests/Unit/SaleServiceTest.php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Sale;
use App\Models\User;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Pricing;
use App\Models\CashRegister;
use App\Models\PointOfSale;
use App\Models\CashRegisterSession;
use App\Models\CashTransaction;
use App\Services\SaleService;
use App\Exceptions\SaleServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SaleService $saleService;
    protected User $user;
    protected Payment $cashPayment;
    protected Payment $cardPayment;
    protected CashRegister $cashRegister;
    protected PointOfSale $pointOfSale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->saleService = new SaleService();
      
        $this->user = User::factory()->create();

        $this->pointOfSale = PointOfSale::create([
            'name' => 'Point de Vente Principal',
            'address' => '123 Rue Principale',
            'city' => 'Antananarivo',
            'phone' => '0341234567',
            'is_active' => true
        ]);

        $this->cashRegister = CashRegister::create([
            'name' => 'Caisse Principale',
            'point_of_sale_id' => $this->pointOfSale->id,
            'ip_address' => '192.168.1.100',
            'is_active' => true
        ]);

        $this->cashPayment = Payment::create([
            'name' => 'Espèces',
            'is_active' => true
        ]);

        $this->cardPayment = Payment::create([
            'name' => 'Carte bancaire',
            'is_active' => true
        ]);
    }

    protected function createProductWithPrice($price = 100): Product
    {
        $product = Product::factory()->create();

        Pricing::create([
            'point_of_sale_id' => $this->pointOfSale->id,
            'product_id' => $product->id,
            'price' => $price
        ]);

        return $product;
    }

    #[Test]
    public function it_creates_cash_transaction_for_cash_sale(): void
    {
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'is_closed' => false,
            'start_ticket_number' => 1,
            'opened_at' => now()
        ]);

        $product = $this->createProductWithPrice(100);

        $saleData = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cashPayment->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $sale = $this->saleService->createSale($saleData, $this->user);

        $this->assertNotNull($sale);
        $this->assertEquals('completed', $sale->status);
        $this->assertEquals(200, $sale->final_amount);
        $this->assertEquals(1, $sale->ticket_number);

        $this->assertTrue($sale->cashTransaction()->exists());
        $this->assertEquals('sale', $sale->cashTransaction->type);
        $this->assertEquals($sale->final_amount, $sale->cashTransaction->amount);
        $this->assertEquals($session->id, $sale->cashTransaction->session_id);
    }

    #[Test]
    public function it_does_not_create_cash_transaction_for_card_sale(): void
    {
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'is_closed' => false,
            'start_ticket_number' => 1,
            'opened_at' => now()
        ]);

        $product = $this->createProductWithPrice(100);

        $saleData = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cardPayment->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $sale = $this->saleService->createSale($saleData, $this->user);

        $this->assertFalse($sale->cashTransaction()->exists());
        $this->assertEquals(200, $sale->final_amount);
    }

    #[Test]
    public function it_cancels_sale_and_creates_refund_transaction_for_cash_sale(): void
    {
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'total_sales' => 0,
            'is_closed' => false,
            'start_ticket_number' => 1,
            'opened_at' => now()
        ]);

        $product = $this->createProductWithPrice(100);

        $saleData = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cashPayment->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $sale = $this->saleService->createSale($saleData, $this->user);

        $this->assertTrue($sale->cashTransaction()->exists());

        $session->refresh();
        $this->assertEquals(200, $session->total_sales);

        $cancelledSale = $this->saleService->cancelSale($sale, 'Test annulation');

        $this->assertEquals('cancelled', $cancelledSale->status);
        $this->assertNotNull($cancelledSale->cancelled_at);
        $this->assertEquals('Test annulation', $cancelledSale->cancellation_reason);

        $cashTransaction = $cancelledSale->cashTransaction()->first();
        $this->assertEquals('refund', $cashTransaction->type);
        $this->assertStringContainsString('ANNULÉE', $cashTransaction->description);

        $session->refresh();
        $this->assertEquals(0, $session->total_sales);
    }

    #[Test]
    public function it_updates_session_total_sales_on_cash_sale(): void
    {
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'total_sales' => 0,
            'is_closed' => false,
            'start_ticket_number' => 1,
            'opened_at' => now()
        ]);

        $product = $this->createProductWithPrice(100);

        $saleData = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cashPayment->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $sale = $this->saleService->createSale($saleData, $this->user);

        $session->refresh();

        $this->assertEquals($sale->final_amount, $session->total_sales);
        $this->assertEquals(200, $session->total_sales);
    }

    #[Test]
    public function it_generates_incremental_ticket_numbers(): void
    {
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'is_closed' => false,
            'start_ticket_number' => 100,
            'opened_at' => now()
        ]);

        $product = $this->createProductWithPrice(100);

        $saleData1 = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cashPayment->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $sale1 = $this->saleService->createSale($saleData1, $this->user);
        $this->assertEquals(100, $sale1->ticket_number);

        $saleData2 = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cashPayment->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $sale2 = $this->saleService->createSale($saleData2, $this->user);
        $this->assertEquals(101, $sale2->ticket_number);

        $saleData3 = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cashPayment->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $sale3 = $this->saleService->createSale($saleData3, $this->user);
        $this->assertEquals(102, $sale3->ticket_number);
    }

    #[Test]
    public function it_throws_exception_when_session_is_closed(): void
    {
        $this->expectException(SaleServiceException::class);

        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'is_closed' => true,
            'start_ticket_number' => 1,
            'opened_at' => now(),
            'closed_at' => now()
        ]);

        $product = $this->createProductWithPrice(100);

        $saleData = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cashPayment->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $this->saleService->createSale($saleData, $this->user);
    }

    #[Test]
    public function it_calculates_totals_correctly_with_discount(): void
    {
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'is_closed' => false,
            'start_ticket_number' => 1,
            'opened_at' => now()
        ]);

        $product1 = $this->createProductWithPrice(100);
        $product2 = $this->createProductWithPrice(150);

        $saleData = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cashPayment->id,
            'items' => [
                [
                    'product_id' => $product1->id,
                    'quantity' => 2,
                    'unit_price' => 100
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 1,
                    'unit_price' => 150
                ]
            ],
            'discount_percentage' => 10,
            'status' => 'completed'
        ];

        $sale = $this->saleService->createSale($saleData, $this->user);

        $expectedTotal = 350;
        $expectedFinal = 315;

        $this->assertEquals($expectedTotal, $sale->total_amount);
        $this->assertEquals(10, $sale->discount_percentage);
        $this->assertEquals($expectedFinal, $sale->final_amount);
        $this->assertEquals(2, $sale->orderlines()->count());
    }

    #[Test]
    public function it_handles_multiple_payments(): void
    {
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'is_closed' => false,
            'start_ticket_number' => 1,
            'opened_at' => now()
        ]);

        $product = $this->createProductWithPrice(100);

        $saleData = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 100
                ]
            ],
            'payments' => [
                [
                    'payment_id' => $this->cashPayment->id,
                    'amount' => 200
                ],
                [
                    'payment_id' => $this->cardPayment->id,
                    'amount' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $sale = $this->saleService->createSale($saleData, $this->user);

        $this->assertEquals(2, $sale->payments()->count());
        $this->assertEquals(300, $sale->amount_received);
        $this->assertEquals(0, $sale->change_amount);
        $this->assertTrue($sale->cashTransaction()->exists());
    }

    #[Test]
    public function it_calculates_change_correctly(): void
    {
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'is_closed' => false,
            'start_ticket_number' => 1,
            'opened_at' => now()
        ]);

        $product = $this->createProductWithPrice(100);

        $saleData = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cashPayment->id,
            'amount_received' => 250,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $sale = $this->saleService->createSale($saleData, $this->user);

        $this->assertEquals(200, $sale->final_amount);
        $this->assertEquals(250, $sale->amount_received);
        $this->assertEquals(50, $sale->change_amount);
    }

    #[Test]
    public function it_throws_exception_when_cancelling_already_cancelled_sale(): void
    {
        $this->expectException(SaleServiceException::class);

        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'is_closed' => false,
            'start_ticket_number' => 1,
            'opened_at' => now()
        ]);

        $product = $this->createProductWithPrice(100);

        $saleData = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cashPayment->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $sale = $this->saleService->createSale($saleData, $this->user);

        $this->saleService->cancelSale($sale, 'Première annulation');
        $this->saleService->cancelSale($sale, 'Deuxième annulation');
    }

    #[Test]
    public function it_does_not_create_refund_transaction_for_card_sale_cancellation(): void
    {
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'is_closed' => false,
            'start_ticket_number' => 1,
            'opened_at' => now()
        ]);

        $product = $this->createProductWithPrice(100);

        $saleData = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cardPayment->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100
                ]
            ],
            'status' => 'completed'
        ];

        $sale = $this->saleService->createSale($saleData, $this->user);

        $this->assertFalse($sale->cashTransaction()->exists());

        $cancelledSale = $this->saleService->cancelSale($sale, 'Test annulation');

        $this->assertFalse($cancelledSale->cashTransaction()->exists());
        $this->assertEquals('cancelled', $cancelledSale->status);
    }

    #[Test]
    public function it_creates_sale_with_notes(): void
    {
        $session = CashRegisterSession::create([
            'cash_register_id' => $this->cashRegister->id,
            'user_id' => $this->user->id,
            'starting_amount' => 1000,
            'is_closed' => false,
            'start_ticket_number' => 1,
            'opened_at' => now()
        ]);

        $product = $this->createProductWithPrice(100);

        $notes = "Client VIP - Demande spéciale";

        $saleData = [
            'user_id' => $this->user->id,
            'point_of_sale_id' => $this->pointOfSale->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $this->cashPayment->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100
                ]
            ],
            'notes' => $notes,
            'status' => 'completed'
        ];

        $sale = $this->saleService->createSale($saleData, $this->user);

        $this->assertEquals($notes, $sale->notes);
    }
}