<?php
// app/Services/SaleService.php

namespace App\Services;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\CashRegisterSession;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Exceptions\SaleServiceException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleService
{
    protected function isCashPayment($paymentId): bool
    {
        $payment = Payment::find($paymentId);
        return $payment && $payment->name === 'Espèces';
    }

    protected function getMainPaymentId(Sale $sale): ?int
    {
        $mainPayment = $sale->payments()->first();
        return $mainPayment ? $mainPayment->payment_id : null;
    }

    protected function generateTicketNumber(CashRegisterSession $session): int
    {
        return DB::transaction(function () use ($session) {
            $lockedSession = CashRegisterSession::where('id', $session->id)->lockForUpdate()->first();
            $count = Sale::where('cash_register_session_id', $session->id)->count();
            return ($lockedSession->start_ticket_number ?? 1) + $count;
        });
    }

    protected function validatePayments(?array $payments, float $finalAmount): void
    {
        if (empty($payments)) return;
        $totalPaid = collect($payments)->sum('amount');
        if ($totalPaid < $finalAmount - 0.01) {
            throw SaleServiceException::insufficientPayment($totalPaid, $finalAmount);
        }
    }

    protected function createCashTransaction(Sale $sale, CashRegisterSession $session, string $type = 'sale'): ?CashTransaction
    {
        $paymentId = $this->getMainPaymentId($sale);
        if (!$this->isCashPayment($paymentId)) return null;
        if ($sale->cashTransaction()->exists()) return null;

        return CashTransaction::create([
            'session_id' => $session->id,
            'sale_id' => $sale->id,
            'type' => $type,
            'amount' => $sale->final_amount,
            'description' => "Vente #{$sale->ticket_number}",
            'reference' => $sale->ticket_number,
            'created_by' => $sale->user_id,
            'notes' => "Vente validée le " . now()->format('d/m/Y H:i'),
        ]);
    }

    protected function createRefundTransaction(Sale $sale, CashRegisterSession $session, ?string $reason = null): ?CashTransaction
    {
        $paymentId = $this->getMainPaymentId($sale);
        if (!$this->isCashPayment($paymentId)) return null;
        
        return CashTransaction::create([
            'session_id' => $session->id,
            'sale_id' => $sale->id,
            'type' => 'refund',
            'amount' => $sale->final_amount,
            'description' => "Remboursement vente #{$sale->ticket_number}",
            'reference' => $sale->ticket_number,
            'created_by' => auth()->id(),
            'notes' => "Annulation: " . ($reason ?? 'Sans raison')
        ]);
    }

    protected function calculateAmounts(array $items, ?array $data): array
    {
        $calculatedTotal = collect($items)->sum(fn($item) => $item['quantity'] * $item['unit_price']);
        $totalAmount = (float) ($data['total_amount'] ?? $calculatedTotal);
        $discount = (float) ($data['discount_percentage'] ?? 0);
        $finalAmount = round($totalAmount * (1 - ($discount / 100)), 2);
        return ['total_amount' => $totalAmount, 'discount' => $discount, 'final_amount' => $finalAmount];
    }

    protected function processPayments(array $data, Sale $sale, float $finalAmount): array
    {
        $amountReceived = 0;
        
        if (isset($data['payments']) && is_array($data['payments'])) {
            $this->validatePayments($data['payments'], $finalAmount);
            $amountReceived = collect($data['payments'])->sum('amount');
            foreach ($data['payments'] as $payment) {
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'payment_id' => $payment['payment_id'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'notes' => $payment['notes'] ?? null,
                ]);
            }
        } else if (isset($data['payment_id'])) {
            $amountReceived = (float) ($data['amount_received'] ?? $finalAmount);
            SalePayment::create([
                'sale_id' => $sale->id,
                'payment_id' => $data['payment_id'],
                'amount' => $amountReceived,
                'reference' => $data['payment_reference'] ?? null,
                'notes' => $data['payment_notes'] ?? null,
            ]);
        } else {
            $amountReceived = (float) ($data['amount_received'] ?? $finalAmount);
        }
        
        return [
            'amount_received' => $amountReceived,
            'change_amount' => max(0, round($amountReceived - $finalAmount, 2))
        ];
    }

    public function createSale(array $data, $user): Sale
    {
        try {
            return DB::transaction(function () use ($data, $user) {
                $session = CashRegisterSession::lockForUpdate()->findOrFail($data['cash_register_session_id']);
                if ($session->is_closed) throw SaleServiceException::sessionClosed($session->id);
                
                $ticketNumber = $this->generateTicketNumber($session);
                $amounts = $this->calculateAmounts($data['items'], $data);
                
                $sale = Sale::create([
                    'user_id' => $data['user_id'],
                    'point_of_sale_id' => $data['point_of_sale_id'],
                    'cash_register_session_id' => $data['cash_register_session_id'],
                    'table_id' => $data['table_id'] ?? null,
                    'total_amount' => $amounts['total_amount'],
                    'discount_percentage' => $amounts['discount'],
                    'final_amount' => $amounts['final_amount'],
                    'status' => $data['status'] ?? 'completed',
                    'ticket_number' => $ticketNumber,
                    'notes' => $data['notes'] ?? null,
                ]);
                
                foreach ($data['items'] as $item) {
                    $sale->orderlines()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'price' => $item['unit_price'],
                        'total' => $item['quantity'] * $item['unit_price'],
                    ]);
                }
                
                $paymentData = $this->processPayments($data, $sale, $amounts['final_amount']);
                $sale->update(['amount_received' => $paymentData['amount_received'], 'change_amount' => $paymentData['change_amount']]);
                
                if ($sale->status === 'completed') {
                    $session->increment('total_sales', $sale->final_amount);
                    $this->createCashTransaction($sale, $session, 'sale');
                }
                
                return $sale->load(['orderlines.product', 'payments.payment', 'table', 'user', 'cashTransaction']);
            });
        } catch (SaleServiceException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Échec création vente", ['error' => $e->getMessage(), 'data' => $data]);
            throw new SaleServiceException("Erreur création vente: " . $e->getMessage(), 500, $e);
        }
    }

    public function createPendingOrder(array $data, $user): Sale
    {
        try {
            return DB::transaction(function () use ($data, $user) {
                $session = CashRegisterSession::lockForUpdate()->findOrFail($data['cash_register_session_id']);
                if ($session->is_closed) {
                    throw new SaleServiceException("La session de caisse est fermée.");
                }
                
                $totalAmount = collect($data['order_lines'])->sum(fn($line) => $line['quantity'] * $line['price']);
                $discount = $data['discount_percentage'] ?? 0;
                $finalAmount = $totalAmount * (1 - ($discount / 100));
                
                $maxTicket = Sale::where('cash_register_session_id', $data['cash_register_session_id'])->max('ticket_number');
                $ticketNumber = $maxTicket ? $maxTicket + 1 : ($session->start_ticket_number ?? 1);
                
                $sale = Sale::create([
                    'user_id' => $data['user_id'],
                    'point_of_sale_id' => $data['point_of_sale_id'],
                    'cash_register_session_id' => $data['cash_register_session_id'],
                    'table_id' => $data['table_id'],
                    'total_amount' => $totalAmount,
                    'discount_percentage' => $discount,
                    'final_amount' => $finalAmount,
                    'status' => 'pending',
                    'ticket_number' => $ticketNumber,
                ]);
                
                foreach ($data['order_lines'] as $line) {
                    $sale->orderLines()->create([
                        'product_id' => $line['product_id'],
                        'quantity' => $line['quantity'],
                        'price' => $line['price'],
                        'total' => $line['quantity'] * $line['price'],
                    ]);
                }
                
                $table = \App\Models\Table::find($data['table_id']);
                if ($table) {
                    $table->update(['status' => 'occupied']);
                }
                
                return $sale->load(['orderLines.product', 'table']);
            });
        } catch (\Exception $e) {
            Log::error("Échec création commande en attente", ['error' => $e->getMessage(), 'data' => $data]);
            throw new SaleServiceException("Erreur création commande: " . $e->getMessage(), 500, $e);
        }
    }

    public function addToPendingOrder(Sale $sale, array $orderLines): Sale
    {
        try {
            return DB::transaction(function () use ($sale, $orderLines) {
                if ($sale->status !== 'pending') {
                    throw new SaleServiceException("Seules les commandes en attente peuvent être modifiées.");
                }
                
                foreach ($orderLines as $line) {
                    $sale->orderLines()->create([
                        'product_id' => $line['product_id'],
                        'quantity' => $line['quantity'],
                        'price' => $line['price'],
                        'total' => $line['quantity'] * $line['price'],
                    ]);
                }
                
                $totalAmount = $sale->orderLines()->sum('total');
                $finalAmount = $totalAmount * (1 - ($sale->discount_percentage / 100));
                
                $sale->update([
                    'total_amount' => $totalAmount,
                    'final_amount' => $finalAmount,
                ]);
                
                return $sale->load(['orderLines.product', 'table']);
            });
        } catch (\Exception $e) {
            Log::error("Échec ajout commande", ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
            throw new SaleServiceException("Erreur ajout produits: " . $e->getMessage(), 500, $e);
        }
    }

    public function removeFromPendingOrder(Sale $sale, array $orderLineIds): Sale
    {
        try {
            return DB::transaction(function () use ($sale, $orderLineIds) {
                if ($sale->status !== 'pending') {
                    throw new SaleServiceException("Seules les commandes en attente peuvent être modifiées.");
                }
                
                $sale->orderLines()->whereIn('id', $orderLineIds)->delete();
                
                $totalAmount = $sale->orderLines()->sum('total');
                $finalAmount = $totalAmount * (1 - ($sale->discount_percentage / 100));
                
                $sale->update([
                    'total_amount' => $totalAmount,
                    'final_amount' => $finalAmount,
                ]);
                
                return $sale->load(['orderLines.product', 'table']);
            });
        } catch (\Exception $e) {
            Log::error("Échec suppression commande", ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
            throw new SaleServiceException("Erreur suppression produits: " . $e->getMessage(), 500, $e);
        }
    }

    public function validatePendingOrder(Sale $sale, array $data): Sale
    {
        try {
            return DB::transaction(function () use ($sale, $data) {
                if ($sale->status !== 'pending') {
                    throw new SaleServiceException("Seule une commande en attente peut être validée.");
                }
                
                if ($sale->orderLines->isEmpty()) {
                    throw new SaleServiceException("La commande ne contient aucun produit.");
                }
                
                $totalAmount = $sale->orderLines()->sum('total');
                $discount = $data['discount_percentage'] ?? $sale->discount_percentage;
                $finalAmount = $totalAmount * (1 - ($discount / 100));
                
                $amountReceived = $data['amount_received'] ?? $finalAmount;
                $changeAmount = $data['change_amount'] ?? max(0, $amountReceived - $finalAmount);
                
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'payment_id' => $data['payment_id'],
                    'amount' => $amountReceived,
                ]);
                
                $sale->update([
                    'status' => 'completed',
                    'total_amount' => $totalAmount,
                    'discount_percentage' => $discount,
                    'final_amount' => $finalAmount,
                    'amount_received' => $amountReceived,
                    'change_amount' => $changeAmount,
                ]);
                
                $session = $sale->cashRegisterSession;
                if ($session) {
                    $session->increment('total_sales', $finalAmount);
                }
                
                if ($sale->table) {
                    $sale->table->update(['status' => 'available']);
                }
                
                return $sale->load(['orderLines.product', 'table', 'payments.payment']);
            });
        } catch (\Exception $e) {
            Log::error("Échec validation commande", ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
            throw new SaleServiceException("Erreur validation commande: " . $e->getMessage(), 500, $e);
        }
    }

    public function cancelSale(Sale $sale, ?string $reason = null): Sale
    {
        try {
            return DB::transaction(function () use ($sale, $reason) {
                if ($sale->status === 'cancelled') {
                    throw SaleServiceException::alreadyCancelled($sale->id);
                }
                
                $session = $sale->cashRegisterSession;
                
                if ($sale->status === 'completed') {
                    $session->decrement('total_sales', $sale->final_amount);
                    
                    $cashTransaction = CashTransaction::where('sale_id', $sale->id)->first();
                    if ($cashTransaction) {
                        $cashTransaction->update([
                            'type' => 'refund',
                            'description' => "Remboursement vente #{$sale->ticket_number} - ANNULÉE",
                            'notes' => "Annulation: " . ($reason ?? 'Sans raison')
                        ]);
                    }
                }
                
                $sale->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason
                ]);
                
                if ($sale->table && $sale->table->status === 'occupied') {
                    $sale->table->update(['status' => 'available']);
                }
                
                $sale->refresh();
                return $sale->load(['cashTransaction', 'orderLines.product', 'payments.payment']);
            });
        } catch (SaleServiceException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Échec annulation vente", ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
            throw new SaleServiceException("Erreur annulation vente: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Récupère les données d'une vente formatées par catégorie
     */
    public function getFormattedSaleData(Sale $sale): array
    {
        $categoriesWithItems = [];
        
        foreach ($sale->orderLines as $line) {
            $categoryName = $line->product->category->name ?? 'Sans catégorie';
            $categoryId = $line->product->category->id ?? null;
            
            if (!isset($categoriesWithItems[$categoryName])) {
                $categoriesWithItems[$categoryName] = [
                    'name' => $categoryName,
                    'id' => $categoryId,
                    'items' => [],
                    'subtotal' => 0
                ];
            }
            
            $categoriesWithItems[$categoryName]['items'][] = [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => $line->product->name,
                'product_ref' => $line->product->ref ?? null,
                'quantity' => $line->quantity,
                'unit_price' => $line->price,
                'total' => $line->total,
                'notes' => $line->notes ?? null
            ];
            
            $categoriesWithItems[$categoryName]['subtotal'] += $line->total;
        }
        
        $totalAmount = $sale->total_amount;
        $discount = $sale->discount_percentage;
        $finalAmount = $sale->final_amount;
        $paidAmount = $sale->payments->sum('amount') ?? $sale->amount_received;
        $changeAmount = $sale->change_amount ?? max(0, ($paidAmount ?? 0) - $finalAmount);
        
        return [
            'sale' => [
                'id' => $sale->id,
                'ticket_number' => $sale->ticket_number,
                'date' => $sale->created_at->format('d/m/Y H:i'),
                'date_raw' => $sale->created_at,
                'table_id' => $sale->table_id,
                'table_number' => $sale->table?->table_number ?? 'Emporter',
                'cashier_id' => $sale->user_id,
                'cashier_name' => $sale->user?->name ?? 'Inconnu',
                'point_of_sale_id' => $sale->point_of_sale_id,
                'point_of_sale_name' => $sale->pointOfSale?->name ?? 'Restaurant',
                'status' => $sale->status,
                'notes' => $sale->notes,
            ],
            'categories' => array_values($categoriesWithItems),
            'totals' => [
                'subtotal' => $totalAmount,
                'discount_percentage' => $discount,
                'discount_amount' => $totalAmount * ($discount / 100),
                'final_amount' => $finalAmount,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
            ],
            'payments' => $sale->payments->map(function($payment) {
                return [
                    'id' => $payment->id,
                    'method' => $payment->payment->name,
                    'method_code' => $payment->payment->code ?? null,
                    'amount' => $payment->amount,
                    'reference' => $payment->reference,
                    'notes' => $payment->notes,
                ];
            })->values(),
        ];
    }

    /**
     * Récupère uniquement les articles regroupés par catégorie
     */
    public function getItemsGroupedByCategory(Sale $sale): array
    {
        $categories = [];
        
        foreach ($sale->orderLines as $line) {
            $categoryName = $line->product->category->name ?? 'Sans catégorie';
            
            if (!isset($categories[$categoryName])) {
                $categories[$categoryName] = [
                    'category' => $categoryName,
                    'items' => [],
                    'total_quantity' => 0,
                    'subtotal' => 0
                ];
            }
            
            $categories[$categoryName]['items'][] = [
                'product_id' => $line->product_id,
                'product_name' => $line->product->name,
                'quantity' => $line->quantity,
                'unit_price' => $line->price,
                'total' => $line->total,
            ];
            
            $categories[$categoryName]['total_quantity'] += $line->quantity;
            $categories[$categoryName]['subtotal'] += $line->total;
        }
        
        return array_values($categories);
    }

    public function calculateTotals(array $items, float $discount = 0): array
    {
        $totalAmount = collect($items)->sum(fn($item) => $item['quantity'] * $item['unit_price']);
        $finalAmount = round($totalAmount * (1 - ($discount / 100)), 2);
        return [
            'total_amount' => $totalAmount,
            'final_amount' => $finalAmount,
            'discount_amount' => round($totalAmount - $finalAmount, 2),
            'discount_percentage' => $discount
        ];
    }
}