<?php

namespace Database\Seeders;

use App\Models\PointOfSale;
use App\Models\Table;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pointOfSales = PointOfSale::all();

        if ($pointOfSales->isEmpty()) {
            $this->command->error("Aucun Point de Vente trouvé !");
            return;
        }

        // On définit les zones cibles
        $zones = [
            'rdc'       => 'Rez-de-chaussée',
            'etage_1'   => 'Étage 1',
            'exterieur' => 'Extérieur',
            'terrasse'  => 'Terrasse'
        ];

        $this->command->info("Génération de 4 tables par zone pour chaque POS...");

        DB::transaction(function () use ($pointOfSales, $zones) {
            foreach ($pointOfSales as $pos) {
                $globalCounter = 1;

                foreach ($zones as $zoneKey => $zoneLabel) {
                    for ($i = 1; $i <= 4; $i++) {
                        
                        Table::create([
                            'table_number'     => $globalCounter,
                            'name'             => "Table {$i} ({$zoneLabel})",
                            'capacity'         => $this->getCapacityForZone($zoneKey),
                            'status'           => 'available',
                            'point_of_sale_id' => $pos->id,
                            'description'      => "Table située en zone {$zoneLabel}",
                            'location'         => [
                                'zone' => $zoneKey,
                                'x'    => 0,
                                'y'    => 0
                            ]
                        ]);

                        $globalCounter++;
                    }
                }
                $this->command->line("  ✓ " . count($zones) * 4 . " tables créées pour {$pos->name}");
            }
        });

        $this->command->info('Seed terminé avec succès !');
    }

    /**
     * Détermine une capacité logique selon la zone
     */
    private function getCapacityForZone($zone): int
    {
        return match($zone) {
            'terrasse', 'exterieur' => 2, // Petites tables dehors
            'vip' => 8,                  // Grandes tables en VIP
            default => 4,                 // Standard au RDC/Etage
        };
    }
}