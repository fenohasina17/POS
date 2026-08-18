<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Sale;
use App\Models\OrderLine;
use App\Models\PointOfSale;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Table;
use App\Models\User;
use App\Models\Product;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $endDate = today()->startOfDay();
        $startDate = today()->subDays(6)->startOfDay(); // Exactement 1 semaine (7 jours)

        $products = Product::all();
        $payments = collect([
            Payment::firstOrCreate(['name' => 'Espèce']),
            Payment::firstOrCreate(['name' => 'Carte Bancaire']),
            Payment::firstOrCreate(['name' => 'Mobile Money']),
            Payment::firstOrCreate(['name' => 'Chèque']),
        ]);
        $pointsOfSale = PointOfSale::take(4)->get();

        foreach ($pointsOfSale as $pos) {
            // Scope resources to this POS
            $registers = CashRegister::where('point_of_sale_id', $pos->id)->get();
            $users = $pos->assignedUsers->filter(fn($user) => $user->hasRole('caissier'));
            $tables = Table::where('point_of_sale_id', $pos->id)->get();

            if ($registers->isEmpty() || $users->isEmpty() || $tables->isEmpty()) {
                continue;
            }

            // Clear existing sales for this POS
            Sale::where('point_of_sale_id', $pos->id)->delete();
            CashRegisterSession::whereIn('cash_register_id', $registers->pluck('id'))->forceDelete();

            $period = CarbonPeriod::create($startDate, $endDate);
            $ticketSequence = 1;

            foreach ($period as $day) {
                $sessionsForDay = [];
                foreach ($registers as $register) {
                    $sessionsForDay[$register->id] = [];
                    foreach (['morning', 'evening'] as $slot) {
                        $openedAt = $day->copy()->setTime($slot === 'morning' ? 7 : 15, 0);
                        $closedAt = $day->copy()->setTime($slot === 'morning' ? 14 : 23, 59);

                        $sessionsForDay[$register->id][$slot] = CashRegisterSession::create([
                            'cash_register_id' => $register->id,
                            'user_id' => $users->random()->id,
                            'starting_amount' => random_int(100000, 500000),
                            'expected_cash_amount' => 500000,
                            'actual_cash_amount' => 500000,
                            'is_closed' => true,
                            'opened_at' => $openedAt,
                            'closed_at' => $closedAt,
                        ]);
                    }
                }

                $dayOfWeek = $day->dayOfWeekIso; // 1 (Lundi) à 7 (Dimanche)
                
                // Modèle prévisible des ventes pour avoir une belle courbe connue
                $salesPattern = [
                    1 => 10, // Lundi : calme
                    2 => 15, // Mardi : hausse légère
                    3 => 25, // Mercredi : pic milieu de semaine
                    4 => 20, // Jeudi : baisse légère
                    5 => 35, // Vendredi : forte hausse (début de weekend)
                    6 => 50, // Samedi : le plus gros jour de la semaine
                    7 => 30, // Dimanche : bonne journée mais calme le soir
                ];

                $dailySalesCount = $salesPattern[$dayOfWeek] ?? 15;

                for ($i = 0; $i < $dailySalesCount; $i++) {
                    $register = $registers->random();
                    $user = $users->random();
                    $table = $tables->random();

                    // Distribution pondérée pour créer des pics (midi et soir)
                    $rand = random_int(1, 100);
                    if ($rand <= 40) {
                        $hour = random_int(12, 13); // Pic du midi (40%)
                    } elseif ($rand <= 80) {
                        $hour = random_int(19, 21); // Pic du soir (40%)
                    } elseif ($rand <= 90) {
                        $hour = random_int(8, 11);  // Matin (10%)
                    } else {
                        $hour = random_int(14, 18); // Après-midi (10%)
                    }
                    $saleDateTime = $day->copy()->setTime($hour, random_int(0, 59));
                    $slot = $saleDateTime->hour < 15 ? 'morning' : 'evening';
                    $session = $sessionsForDay[$register->id][$slot];

                    $sale = Sale::create([
                        'ticket_number' => sprintf('%s-%s-%04d', $pos->code ?? 'POS', $day->format('Ymd'), $ticketSequence++),
                        'user_id' => $user->id,
                        'point_of_sale_id' => $pos->id,
                        'table_id' => $table->id,
                        'cash_register_session_id' => $session->id,
                        'total_amount' => 0,
                        'final_amount' => 0,
                        'status' => 'completed',
                        'created_at' => $saleDateTime,
                    ]);

                    // Add items
                    $lineCount = random_int(1, 6);
                    $saleTotal = 0;
                    for ($j = 0; $j < $lineCount; $j++) {
                        $product = $products->random();
                        $qty = random_int(1, 3);
                        $price = random_int(2000, 30000);
                        OrderLine::create([
                            'sale_id' => $sale->id,
                            'product_id' => $product->id,
                            'quantity' => $qty,
                            'price' => $price,
                            'total' => $qty * $price,
                        ]);
                        $saleTotal += ($qty * $price);
                    }

                    // Simulation de marketing: environ 15% des ventes bénéficient d'une remise (Happy Hour, Fidélité...)
                    $discountPct = 0;
                    if (random_int(1, 100) <= 15) {
                        $discountPct = collect([5, 10, 15, 20])->random();
                    }
                    $finalAmount = $saleTotal * (1 - ($discountPct / 100));

                    $sale->update([
                        'total_amount' => $saleTotal,
                        'final_amount' => $finalAmount,
                        'discount_percentage' => $discountPct,
                    ]);

                    // Sélection d'un moyen de paiement (pondéré ou aléatoire, ici aléatoire)
                    $payment = $payments->random();
                    
                    \App\Models\SalePayment::create([
                        'sale_id' => $sale->id,
                        'payment_id' => $payment->id,
                        'amount' => $finalAmount,
                    ]);
                }
            }
        }
    }
}
