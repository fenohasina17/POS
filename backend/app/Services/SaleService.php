<?php
// app/Services/SaleService.php

namespace App\Services;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\CashRegisterSession;
use App\Models\CashTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\SaleServiceException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleService
{
    /**
     * Vérifie si un mode de paiement est "Espèces"
     *
     * @param int $paymentId ID du mode de paiement (clé étrangère vers payments)
     * @return bool True si le paiement est en espèces, false sinon
     */
    protected function isCashPayment(int $paymentId): bool
    {
        return Cache::remember("payment.{$paymentId}.is_cash", 86400, function() use ($paymentId) {
            $payment = Payment::find($paymentId);
            return $payment && $payment->name === 'Espèces';
        });
    }

    /**
     * Récupère l'ID du premier paiement associé à une vente
     *
     * @param Sale $sale Instance de la vente
     * @return int|null ID du paiement ou null si aucun paiement
     */
    protected function getMainPaymentId(Sale $sale): ?int
    {
        $mainPayment = $sale->payments()->first();
        return $mainPayment ? $mainPayment->payment_id : null;
    }

    /**
     * Génère un numéro de ticket incrémental pour une session de caisse
     * Utilise lockForUpdate() pour éviter les doublons en environnement concurrent
     *
     * @param CashRegisterSession $session Session de caisse active
     * @return int Numéro de ticket unique pour la session
     * 
     * @throws \Illuminate\Database\QueryException En cas d'erreur de verrouillage BD
     */
    protected function generateTicketNumber(CashRegisterSession $session): int
    {
        return DB::transaction(function () use ($session) {
            // Verrouiller la session pour éviter les doublons de numéros en cas d'appels simultanés
            $lockedSession = CashRegisterSession::where('id', $session->id)->lockForUpdate()->first();
            
            // On cherche le numéro le plus élevé déjà utilisé dans cette session (incluant les ventes supprimées)
            $maxUsed = Sale::withTrashed()
                ->where('cash_register_session_id', $session->id)
                ->max('ticket_number');

            // Le numéro de départ défini pour cette session
            $startNumber = $lockedSession->start_ticket_number ?? 1;

            // On prend le maximum entre le numéro de départ et le suivant disponible
            return max($startNumber, ($maxUsed ? $maxUsed + 1 : 0));
        });
    }

    /**
     * Valide que le montant total payé est suffisant par rapport au montant final
     *
     * @param array|null $payments Tableau des paiements [['payment_id' => int, 'amount' => float], ...]
     * @param float $finalAmount Montant total à payer (après remise)
     * @return void
     * 
     * @throws SaleServiceException Si total payé < montant final (tolérance 0.01)
     */
    protected function validatePayments(?array $payments, float $finalAmount): void
    {
        if (empty($payments))
            return;
        $totalPaid = collect($payments)->sum('amount');
        if ($totalPaid < $finalAmount - 0.01) {
            throw SaleServiceException::insufficientPayment($totalPaid, $finalAmount);
        }
    }

    /**
     * Crée une transaction cash pour une vente (entrée d'argent dans la caisse)
     * Ne fait rien si le paiement n'est pas en espèces ou si une transaction existe déjà
     *
     * @param Sale $sale Vente concernée
     * @param CashRegisterSession $session Session de caisse active
     * @param string $type Type de transaction ('sale' par défaut)
     * @return CashTransaction|null Transaction créée ou null si non-espèces ou déjà existante
     */
    protected function createCashTransaction(Sale $sale, CashRegisterSession $session, string $type = 'sale'): ?CashTransaction
    {
        $paymentId = $this->getMainPaymentId($sale);
        if (!$this->isCashPayment($paymentId))
            return null;
        if ($sale->cashTransaction()->exists())
            return null;

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

    /**
     * Crée une transaction de remboursement pour une annulation (sortie d'argent)
     *
     * @param Sale $sale Vente annulée
     * @param CashRegisterSession $session Session de caisse active
     * @param string|null $reason Motif de l'annulation (optionnel)
     * @return CashTransaction|null Transaction créée ou null si non-espèces
     */
    protected function createRefundTransaction(Sale $sale, CashRegisterSession $session, ?string $reason = null): ?CashTransaction
    {
        $paymentId = $this->getMainPaymentId($sale);
        if (!$this->isCashPayment($paymentId))
            return null;

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

    /**
     * Calcule les montants d'une vente à partir des articles
     *
     * @param array $items Articles vendus [['quantity' => int, 'unit_price' => float], ...]
     * @param array|null $data Données optionnelles contenant total_amount et discount_percentage
     * @return array ['total_amount' => float, 'discount' => float, 'final_amount' => float]
     */
    protected function calculateAmounts(array $items, ?array $data): array
    {
        $calculatedTotal = collect($items)->sum(fn($item) => $item['quantity'] * $item['unit_price']);
        $totalAmount = (float) ($data['total_amount'] ?? $calculatedTotal);
        $discount = (float) ($data['discount_percentage'] ?? 0);
        $finalAmount = round($totalAmount * (1 - ($discount / 100)), 2);
        return ['total_amount' => $totalAmount, 'discount' => $discount, 'final_amount' => $finalAmount];
    }

    /**
     * Traite les paiements et les associe à une vente
     * Supporte 3 formats : multi-paiements, paiement unique, ou simple montant reçu
     *
     * @param array $data Données contenant les paiements
     *                    Format 1 : ['payments' => [['payment_id' => int, 'amount' => float], ...]]
     *                    Format 2 : ['payment_id' => int, 'amount_received' => float]
     *                    Format 3 : ['amount_received' => float]
     * @param Sale $sale Vente concernée
     * @param float $finalAmount Montant final à payer
     * @return array ['amount_received' => float, 'change_amount' => float]
     * 
     * @throws SaleServiceException Si paiement insuffisant
     */
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

    /**
     * Crée une vente complète (payée immédiatement)
     *
     * @param array $data Données de la vente
     *                    REQUIS :
     *                    - user_id (int) : ID du caissier
     *                    - point_of_sale_id (int) : ID du point de vente
     *                    - cash_register_session_id (int) : ID de la session caisse
     *                    - items (array) : [['product_id' => int, 'quantity' => int, 'unit_price' => float], ...]
     *                    OPTIONNELS :
     *                    - table_id (int|null) : ID de la table (null = emporter)
     *                    - discount_percentage (float) : Remise en % (défaut: 0)
     *                    - status (string) : 'completed' par défaut
     *                    - notes (string|null) : Note optionnelle
     *                    - payments (array) : Format multi-paiements
     *                    - payment_id (int) : Format paiement unique
     *                    - amount_received (float) : Montant reçu
     * @param mixed $user Utilisateur connecté (pour audit)
     * @return Sale Vente créée avec relations chargées (orderlines.product, payments.payment, table, user, cashTransaction)
     * 
     * @throws SaleServiceException Si session fermée, paiement insuffisant, ou erreur générique
     */
    public function createSale(array $data, $user): Sale
    {
        try {
            return DB::transaction(function () use ($data, $user) {
                $session = CashRegisterSession::lockForUpdate()->findOrFail($data['cash_register_session_id']);
                if ($session->is_closed)
                    throw SaleServiceException::sessionClosed($session->id);

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

    /**
     * Crée une commande en attente (non payée)
     * Utilisé pour les commandes en salle avant paiement
     *
     * @param array $data Données de la commande
     *                    REQUIS :
     *                    - user_id (int) : ID du caissier
     *                    - point_of_sale_id (int) : ID du point de vente
     *                    - cash_register_session_id (int) : ID de la session caisse
     *                    - table_id (int) : ID de la table (obligatoire pour commande en salle)
     *                    - order_lines (array) : [['product_id' => int, 'quantity' => int, 'price' => float], ...]
     *                    OPTIONNELS :
     *                    - discount_percentage (float) : Remise en % (défaut: 0)
     * @param mixed $user Utilisateur connecté
     * @return Sale Commande en attente avec relations chargées (orderLines.product, table)
     * 
     * @throws SaleServiceException Si session fermée ou erreur création
     */
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

                $ticketNumber = $this->generateTicketNumber($session);

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

    /**
     * Ajoute des articles à une commande en attente existante
     *
     * @param Sale $sale Commande existante (status MUST BE 'pending')
     * @param array $orderLines Nouveaux articles à ajouter
     *                          [['product_id' => int, 'quantity' => int, 'price' => float], ...]
     * @return Sale Commande mise à jour avec relations (orderLines.product, table)
     * 
     * @throws SaleServiceException Si la commande n'est pas en statut 'pending'
     */
    public function addToPendingOrder(Sale $sale, array $orderLines): Sale
    {
        try {
            return DB::transaction(function () use ($sale, $orderLines) {
                if ($sale->status !== 'pending') {
                    throw new SaleServiceException("Seules les commandes en attente peuvent être modifiées.");
                }

                foreach ($orderLines as $line) {
                    // Chercher si le produit existe déjà dans cette commande
                    $existingLine = $sale->orderLines()
                        ->where('product_id', $line['product_id'])
                        ->where('price', $line['price']) // On vérifie aussi le prix au cas où il aurait changé
                        ->first();

                    if ($existingLine) {
                        $newQuantity = $existingLine->quantity + $line['quantity'];
                        $existingLine->update([
                            'quantity' => $newQuantity,
                            'total' => $newQuantity * $existingLine->price,
                        ]);
                    } else {
                        $sale->orderLines()->create([
                            'product_id' => $line['product_id'],
                            'quantity' => $line['quantity'],
                            'price' => $line['price'],
                            'total' => $line['quantity'] * $line['price'],
                        ]);
                    }
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

    /**
     * Supprime des lignes de commande d'une commande en attente
     *
     * @param Sale $sale Commande existante (status MUST BE 'pending')
     * @param array $orderLineIds IDs des lignes de commande à supprimer
     * @return Sale Commande mise à jour avec relations (orderLines.product, table)
     * 
     * @throws SaleServiceException Si la commande n'est pas en statut 'pending'
     */
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

    /**
     * Transforme une commande en attente en vente validée (payée)
     *
     * @param Sale $sale Commande à valider (status MUST BE 'pending')
     * @param array $data Données de paiement
     *                    REQUIS :
     *                    - payment_id (int) : Mode de paiement utilisé
     *                    - amount_received (float) : Montant reçu du client
     *                    OPTIONNELS :
     *                    - change_amount (float) : Monnaie rendue (calculé auto si absent)
     *                    - discount_percentage (float) : Remise finale (écrase celle existante)
     * @return Sale Vente complétée avec relations (orderLines.product, table, payments.payment)
     * 
     * @throws SaleServiceException Si commande vide ou pas en statut 'pending'
     */
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

                // Gestion des paiements (multiples ou unique)
                if (isset($data['payments']) && is_array($data['payments'])) {
                    foreach ($data['payments'] as $payment) {
                        SalePayment::create([
                            'sale_id' => $sale->id,
                            'payment_id' => $payment['payment_id'],
                            'amount' => $payment['amount'],
                            'reference' => $payment['reference'] ?? null,
                            'notes' => $payment['notes'] ?? null,
                        ]);
                    }
                } else {
                    SalePayment::create([
                        'sale_id' => $sale->id,
                        'payment_id' => $data['payment_id'],
                        'amount' => $amountReceived,
                    ]);
                }

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
                    // Création de la transaction cash si nécessaire
                    $this->createCashTransaction($sale, $session, 'sale');
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

    /**
     * Annule une vente (rembourse si déjà payée)
     *
     * @param Sale $sale Vente à annuler
     * @param string|null $reason Motif de l'annulation (optionnel)
     * @return Sale Vente annulée avec status='cancelled', cancelled_at, cancellation_reason
     * 
     * @throws SaleServiceException::alreadyCancelled Si la vente est déjà annulée
     */
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
     * Idéal pour l'affichage de tickets/factures
     *
     * @param Sale $sale Vente concernée
     * @return array Structure complète :
     *               [
     *                 'sale' => [id, ticket_number, date, table_number, cashier_name, ...],
     *                 'categories' => [['name', 'items' => [...], 'subtotal'], ...],
     *                 'totals' => [subtotal, discount_percentage, discount_amount, final_amount, paid_amount, change_amount],
     *                 'payments' => [['method', 'amount', 'reference'], ...]
     *               ]
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
            'payments' => $sale->payments->map(function ($payment) {
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
     * Version simplifiée : récupère uniquement les articles regroupés par catégorie
     * Sans les détails complets de la vente
     *
     * @param Sale $sale Vente concernée
     * @return array [['category' => string, 'items' => array, 'total_quantity' => int, 'subtotal' => float], ...]
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

    /**
     * Calcule les totaux à partir d'une liste d'articles et d'une remise
     * Utilitaire indépendant pour prévisualisation avant création vente
     *
     * @param array $items Articles [['quantity' => int, 'unit_price' => float], ...]
     * @param float $discount Remise en pourcentage (0 par défaut)
     * @return array [
     *               'total_amount' => float,      // Montant avant remise
     *               'final_amount' => float,      // Montant après remise
     *               'discount_amount' => float,   // Montant de la remise
     *               'discount_percentage' => float // Pourcentage appliqué
     *               ]
     */
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