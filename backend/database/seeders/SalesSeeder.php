<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
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

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $startDate = Carbon::create(today()->year, 1, 1)->startOfDay();
        $endDate = today()->startOfDay();

        if ($startDate->greaterThan($endDate)) {
            $startDate = $endDate->copy()->startOfYear();
        }

        $pointOfSale = PointOfSale::find(1);
        if (!$pointOfSale) {
            $pointOfSale = PointOfSale::firstOrCreate(['name' => 'PV101']);
            if (!$pointOfSale->wasRecentlyCreated && $pointOfSale->id !== 1) {
                $pointOfSale->id = 1;
                $pointOfSale->save();
            }
        }

        $cashier = User::role('caissier')->find(5) ?? User::find(5);
        if (!$cashier) {
            $cashier = User::factory()->create([
                'id' => 5,
                'point_of_sale_id' => $pointOfSale->id,
            ]);
        } else {
            $cashier->update(['point_of_sale_id' => $pointOfSale->id]);
        }
        if (!$cashier->hasRole('caissier')) {
            $cashier->assignRole('caissier');
        }
        $cashiers = collect([$cashier]);

        $users = User::where('point_of_sale_id', $pointOfSale->id)->get();
        if ($users->isEmpty()) {
            $users = User::factory()->count(3)->create([
                'point_of_sale_id' => $pointOfSale->id,
            ]);
        }

        $tables = Table::where('point_of_sale_id', $pointOfSale->id)->get();
        $tableIndex = $tables->count();
        while ($tableIndex < 2) {
            $tableIndex++;
            $tables->push(Table::create([
                'table_number' => sprintf('T-%02d', $tableIndex),
                'name' => "Table $tableIndex",
                'capacity' => random_int(2, 6),
                'status' => 'available',
                'description' => 'Table générée pour les ventes KPI.',
                'point_of_sale_id' => $pointOfSale->id,
                'location' => ['x' => $tableIndex * 2, 'y' => $tableIndex * 3],
            ]));
        }

        $products = Product::all();
        if ($products->isEmpty()) {
            $products = Product::factory()->count(25)->create();
        }

        $payment = Payment::firstOrCreate(['name' => 'Espèce']);

        $registers = collect([
            CashRegister::firstOrCreate(
                ['name' => 'Caisse 1', 'point_of_sale_id' => $pointOfSale->id],
                []
            ),
            CashRegister::firstOrCreate(
                ['name' => 'Caisse 2', 'point_of_sale_id' => $pointOfSale->id],
                []
            ),
        ]);

        Sale::where('point_of_sale_id', $pointOfSale->id)->delete();
        CashRegisterSession::whereIn('cash_register_id', $registers->pluck('id'))->forceDelete();

        $salesPerDay = 10;
        $period = CarbonPeriod::create($startDate, $endDate);
        $ticketSequence = 1;

        foreach ($period as $day) {
            $sessionsForDay = [];
            $slots = [
                'morning' => ['start' => [7, 30], 'end' => [14, 59]],
                'evening' => ['start' => [15, 0], 'end' => [23, 0]],
            ];

            foreach ($registers as $registerIndex => $register) {
                $sessionsForDay[$register->id] = [];
                $slotNumber = 0;
                foreach ($slots as $slot => $hours) {
                    $cashier = $cashiers->first();
                    $openedAt = $day->copy()->setTime($hours['start'][0], $hours['start'][1], 0);
                    $closedAt = $day->copy()->setTime($hours['end'][0], $hours['end'][1], 0);

                    $sessionsForDay[$register->id][$slot] = CashRegisterSession::create([
                        'cash_register_id' => $register->id,
                        'user_id' => $cashier->id,
                        'starting_amount' => 500_000,
                        'expected_cash_amount' => 500_000,
                        'actual_cash_amount' => 500_000,
                        'is_closed' => true,
                        'opened_at' => $openedAt,
                        'closed_at' => $closedAt,
                        'start_ticket_number' => $ticketSequence,
                    ]);
                }
            }

            $salesToday = [];
            $targetLinesForDay = random_int(20, 50);
            $remainingLines = $targetLinesForDay;

            for ($i = 0; $i < $salesPerDay; $i++) {
                $register = $registers[$i % $registers->count()];
                $user = $users->random();
                $table = $tables->random();

                $saleDateTime = $day->copy()->setTime(random_int(8, 21), random_int(0, 59), random_int(0, 59));
                $discount = random_int(0, 10);
                $slot = $saleDateTime->hour < 15 ? 'morning' : 'evening';
                $session = $sessionsForDay[$register->id][$slot];

                $sale = Sale::create([
                    'ticket_number' => sprintf('PV101-%s-%04d', $day->format('Ymd'), $ticketSequence++),
                    'user_id' => $user->id,
                    'point_of_sale_id' => $pointOfSale->id,
                    'table_id' => $table->id,
                    'cash_register_session_id' => $session->id,
                    'total_amount' => 0,
                    'discount_percentage' => $discount,
                    'final_amount' => 0,
                    'status' => 'completed',
                    'amount_received' => 0,
                    'change_amount' => 0,
                    'created_at' => $saleDateTime,
                    'updated_at' => $saleDateTime,
                ]);

                $salesToday[] = $sale;
            }

            foreach ($salesToday as $index => $sale) {
                $salesLeft = $salesPerDay - $index - 1;
                if ($salesLeft < 0) {
                    $salesLeft = 0;
                }

                $minLines = max(5, $remainingLines - ($salesLeft * 30));
                $maxLines = max($minLines, min(30, $remainingLines - $salesLeft));
                $lineCount = $salesLeft === 0 ? $remainingLines : random_int($minLines, $maxLines);
                $remainingLines -= $lineCount;

                $saleTotal = 0;

                for ($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++) {
                    $product = $products->random();
                    $quantity = random_int(1, 5);
                    $unitPrice = random_int(2_000, 50_000);
                    $lineTotal = $quantity * $unitPrice;

                    OrderLine::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => $unitPrice,
                        'total' => $lineTotal,
                        'created_at' => $sale->created_at,
                        'updated_at' => $sale->created_at,
                    ]);

                    $saleTotal += $lineTotal;
                }

                $finalAmount = round($saleTotal * (1 - ($sale->discount_percentage / 100)), 2);
                $amountReceived = $finalAmount + random_int(0, 5_000);
                $changeAmount = max(0, $amountReceived - $finalAmount);

                Sale::withoutTimestamps(function () use ($sale, $saleTotal, $finalAmount, $amountReceived, $changeAmount) {
                    $sale->update([
                        'total_amount' => $saleTotal,
                        'final_amount' => $finalAmount,
                        'amount_received' => $amountReceived,
                        'change_amount' => $changeAmount,
                    ]);
                });
            }
        }
    }
}
