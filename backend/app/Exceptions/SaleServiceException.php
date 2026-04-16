<?php
// app/Exceptions/SaleServiceException.php

namespace App\Exceptions;

use Exception;

class SaleServiceException extends Exception
{
    protected $context;

    public function __construct(
        string $message = "", 
        int $code = 0, 
        ?Exception $previous = null, 
        ?array $context = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * Crée une exception pour une session fermée
     */
    public static function sessionClosed(int $sessionId): self
    {
        return new self(
            "La session de caisse #{$sessionId} est fermée. Impossible d'effectuer cette opération.",
            422,
            null,
            ['session_id' => $sessionId]
        );
    }

    /**
     * Crée une exception pour un paiement insuffisant
     */
    public static function insufficientPayment(float $paid, float $expected): self
    {
        return new self(
            "Le montant payé ({$paid}) est insuffisant. Montant attendu: {$expected}",
            422,
            null,
            ['paid' => $paid, 'expected' => $expected]
        );
    }

    /**
     * Crée une exception pour une vente déjà annulée
     */
    public static function alreadyCancelled(int $saleId): self
    {
        return new self(
            "La vente #{$saleId} est déjà annulée.",
            422,
            null,
            ['sale_id' => $saleId]
        );
    }
}