<?php
// app/Services/PrintGroupingService.php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Collection;

class PrintGroupingService
{
    /**
     * Groupe les articles par type d'imprimante
     */
    public function groupItemsByPrinter(Sale $sale): array
    {
        $groups = [
            'kitchen' => [],
            'bar' => [],
            'pizza' => [],
            'receipt' => []
        ];
        
        foreach ($sale->orderLines as $line) {
            $printer = $line->product->category->printer ?? 'kitchen';
            
            switch ($printer) {
                case 'bar':
                    $groups['bar'][] = $line;
                    break;
                case 'pizza':
                    $groups['pizza'][] = $line;
                    break;
                case 'receipt':
                    $groups['receipt'][] = $line;
                    break;
                default:
                    $groups['kitchen'][] = $line;
                    break;
            }
        }
        
        // Supprimer les groupes vides
        return array_filter($groups, fn($items) => !empty($items));
    }
    
    /**
     * Prépare les données pour l'impression
     */
    public function preparePrintData(Sale $sale): array
    {
        $groups = $this->groupItemsByPrinter($sale);
        $printData = [];
        
        foreach ($groups as $printerType => $items) {
            // Vérifier si items est une collection, sinon la convertir
            $itemsCollection = $items instanceof Collection ? $items : collect($items);
            
            $printData[$printerType] = [
                'title' => $this->getPrinterTitle($printerType),
                'items' => $itemsCollection->map(function($item) {
                    return [
                        'name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'notes' => $item->notes ?? null,
                    ];
                })->values()->toArray(),
                'ticket_number' => $sale->ticket_number,
                'table' => $sale->table?->table_number ?? 'Emporter',
                'date' => $sale->created_at->format('d/m/Y H:i'),
            ];
        }
        
        return $printData;
    }
    
    private function getPrinterTitle(string $printerType): string
    {
        return match($printerType) {
            'kitchen' => 'COMMANDE CUISINE',
            'bar' => 'COMMANDE BAR',
            'pizza' => 'COMMANDE PIZZA',
            'receipt' => 'FACTURE',
            default => 'COMMANDE',
        };
    }
}