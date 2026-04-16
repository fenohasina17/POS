<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Printer as PrinterModel;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\PrinterType;
use App\Models\CashRegisterSession;
use App\Services\PrinterScannerService;
use App\Services\LogoPrinter;
use App\Services\CashRegisterSessionSummaryService;
use Illuminate\Support\Carbon;

class PrinterController extends Controller
{
    private ?array $cachedCupsQueues = null;

    /** ---------------------
     * PRINTER CRUD
     * --------------------- */
    public function index()
    {
        $printers = PrinterModel::with('cashRegister.pointOfSale')
            ->when(request('point_of_sale_id'), function($query){
                $query->whereHas('cashRegister', fn($q)=>$q->where('point_of_sale_id', request('point_of_sale_id')));
            })->get();

        return response()->json(['success'=>true,'data'=>$printers]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'cash_register_id' => 'required|exists:cash_registers,id',
                'connection_type' => ['required', Rule::in(['network', 'usb', 'cups'])],
                'ip_address' => ['nullable', 'ip', 'required_if:connection_type,network'],
                'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'usb_identifier' => ['nullable', 'string', 'max:255', 'required_if:connection_type,usb'],
                'timeout' => 'integer|min:1|max:300',
                'is_default' => 'boolean',
                'is_active' => 'boolean',
                'printer_type_id' => 'required|exists:printer_types,id',
            ]);

            $validated = $this->normalizeConnectionFields($validated);

            $printer = PrinterModel::create($validated);

            if($request->boolean('is_default')){
                PrinterModel::where('cash_register_id',$request->cash_register_id)
                    ->where('id','!=',$printer->id)
                    ->update(['is_default'=>false]);
            }

            DB::commit();
            $printer = $printer->fresh(['cashRegister.pointOfSale']);

            return response()->json(['success'=>true,'data'=>$printer,'message'=>'Imprimante créée avec succès'],201);
        } catch (\Exception $e){
            DB::rollBack();
            Log::error("Erreur création imprimante: ".$e->getMessage());
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    public function show(PrinterModel $printer)
    {
        $printer = $printer->fresh(['cashRegister.pointOfSale']);
        return response()->json(['success'=>true,'data'=>$printer]);
    }

    public function update(Request $request, PrinterModel $printer)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'cash_register_id' => 'sometimes|required|exists:cash_registers,id',
            'connection_type' => ['sometimes', 'required', Rule::in(['network', 'usb', 'cups'])],
            'ip_address' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'usb_identifier' => ['nullable', 'string', 'max:255'],
            'timeout' => 'sometimes|integer|min:1|max:300',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'printer_type_id' => 'sometimes|exists:printer_types,id',
        ]);

        $validated = $this->normalizeConnectionFields($validated, $printer);

        $printer->update($validated);
        $printer->refresh();

        return response()->json(['success'=>true,'data'=>$printer]);
    }

    public function destroy(PrinterModel $printer)
    {
        $printer->delete();
        return response()->json(['success'=>true,'message'=>'Imprimante supprimée']);
    }

    /** ---------------------
     * SCAN DES IMPRIMANTES
     * --------------------- */
    public function scanPrinters()
    {
        try {
            $scanner = new PrinterScannerService();
            $printers = $scanner->scanAllPrinters();

            return response()->json([
                'success'=>true,
                'data'=>$printers->toArray(),
                'message'=>$printers->count().' imprimante(s) détectée(s)'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur scan imprimantes: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Erreur scan','data'=>[]],500);
        }
    }

    /** ---------------------
     * CONNECTEUR D’IMPRIMANTE
     * --------------------- */
    private function getConnector(PrinterModel $printer)
    {
        return match ($printer->connection_type) {
            'network' => $this->createNetworkConnector($printer),
            'usb' => $this->createUsbConnector($printer),
            default => $this->createCupsConnector($printer),
        };
    }

    private function createNetworkConnector(PrinterModel $printer)
    {
        if (!$printer->ip_address) {
            throw new \Exception("Adresse IP manquante pour l'imprimante réseau {$printer->name}.");
        }

        $port = $printer->port ?? 9100;
        $timeout = $printer->timeout ?? 30;

        return new NetworkPrintConnector($printer->ip_address, $port, $timeout);
    }

    private function createUsbConnector(PrinterModel $printer)
    {
        if (!$printer->usb_identifier) {
            throw new \Exception("Identifiant USB manquant pour l'imprimante {$printer->name}.");
        }

        return new FilePrintConnector($printer->usb_identifier);
    }

    private function createCupsConnector(PrinterModel $printer)
    {
        if (!$this->isCupsAvailable()) {
            throw new \Exception('CUPS n\'est pas installé ou la commande lpstat est indisponible sur cette machine');
        }

        $printer->loadMissing('printerType');

        $queue = $this->matchQueueByName($printer->name)
            ?? $this->matchQueueByName($printer->printerType?->name)
            ?? $this->getDefaultCupsQueue();

        if (!$queue) {
            throw new \Exception('Aucune imprimante CUPS correspondante trouvée pour '.$printer->name.'. Vérifiez la configuration CUPS.');
        }

        return new CupsPrintConnector($queue);
    }

    private function isCupsAvailable(): bool
    {
        $os = strtoupper(substr(PHP_OS, 0, 3));
        if ($os === 'WIN') {
            return false;
        }

        $checkLpstat = Process::run(['which', 'lpstat']);
        if ($checkLpstat->successful() && trim($checkLpstat->output()) !== '') {
            return true;
        }

        $checkLp = Process::run(['which', 'lp']);
        return $checkLp->successful() && trim($checkLp->output()) !== '';
    }

    private function getDefaultCupsQueue(): ?string
    {
        $queues = $this->getCupsQueues();
        if (empty($queues)) {
            return null;
        }

        $result = Process::run(['lpstat', '-d']);
        if ($result->successful() && preg_match('/system default destination:\s*(\S+)/i', $result->output(), $matches)) {
            $default = $this->matchQueueByName($matches[1]);
            if ($default) {
                return $default;
            }
        }

        return $queues[0] ?? null;
    }

    private function getCupsQueues(): array
    {
        if ($this->cachedCupsQueues !== null) {
            return $this->cachedCupsQueues;
        }

        $result = Process::run(['lpstat', '-p']);
        if (!$result->successful()) {
            $this->cachedCupsQueues = [];
            return $this->cachedCupsQueues;
        }

        $queues = [];
        foreach (preg_split("/\r?\n/", trim($result->output())) as $line) {
            if (!$line) continue;
            if (preg_match('/^printer\s+(\S+)/i', $line, $matches)) {
                $queues[] = trim($matches[1]);
            }
        }

        return $this->cachedCupsQueues = $queues;
    }

    private function matchQueueByName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        foreach ($this->getCupsQueues() as $queue) {
            if (strcasecmp($queue, $name) === 0) {
                return $queue;
            }
        }

        return null;
    }

    /** ---------------------
     * IMPRESSION DE FACTURE
     * --------------------- */
    public function printInvoice($orderId)
    {
        try {
            $order = \App\Models\Sale::with(['orderLines.product.category','cashRegisterSession.cashRegister'])->findOrFail($orderId);
            $user = Auth::user();
            $cashRegister = $order->cashRegisterSession;
            $logoPath = public_path('photos/logo.png');

            $mainPrinterConfig = PrinterModel::where('cash_register_id', $cashRegister->cashRegister->id)->first();
            if(!$mainPrinterConfig) {
                return response()->json(['success'=>false,'message'=>'Aucune imprimante configurée'],400);
            }

            set_time_limit($mainPrinterConfig->timeout ?? 0);

            $cashPrinter = null;
            $response = null;

            try {

                $connector = $this->getConnector($mainPrinterConfig);
                $cashPrinter = new Printer($connector);

                $this->printMainInvoice($cashPrinter, $order, $user, $cashRegister, $logoPath);

                $printerTypes = $this->groupOrderLinesByPrinterType($order);

                if (!empty($printerTypes['cash'])) {
                    $this->printCashTicket($cashPrinter, $printerTypes['cash'], $order, $user, $cashRegister, $logoPath);
                    unset($printerTypes['cash']);
                }

                foreach ($printerTypes as $printerType => $items) {
                    $printerTypeModel = PrinterType::where('name', $printerType)->first();
                    if (!$printerTypeModel) {
                        Log::error("Type d'imprimante inconnu: $printerType");
                        continue;
                    }

                    $printerConfig = PrinterModel::where('printer_type_id', $printerTypeModel->id)->first();
                    if (!$printerConfig) {
                        Log::error("Aucune imprimante configurée pour le type $printerType");
                        continue;
                    }

                    $this->printCategoryOrder($printerConfig, $printerType, $items, $order, $user, $cashRegister, $logoPath);
                }

                $order->update(['printed'=>true]);
                $response = response()->json(['success'=>true,'message'=>'Facture imprimée avec succès']);
            } finally {
                if ($cashPrinter) {
                    try {
                        $cashPrinter->close();
                    } catch (\Throwable $closeError) {
                        Log::warning('Erreur lors de la fermeture de l\'imprimante de caisse: '.$closeError->getMessage());
                    }
                }
            }

        return $response ?? response()->json(['success'=>false,'message'=>'Erreur inconnue lors de l\'impression'],500);

    } catch (\Exception $e) {
        Log::error('Erreur impression: '.$e->getMessage());
        return response()->json(['success'=>false,'message'=>'Erreur: '.$e->getMessage()],500);
    }
}

    public function printSessionRecap($sessionId)
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            $isManager = $user?->hasAnyRole(['gerant', 'gérant'], 'api') ?? false;
            $isAdmin = $user?->hasRole('admin', 'api') ?? false;

            if (!$user || (!$user->hasPermissionTo('print.invoice', 'api') && !$isManager && !$isAdmin)) {
                abort(403, 'Seuls le gérant ou l\'administrateur peuvent imprimer le billetage.');
            }

            $session = CashRegisterSession::with(['cashRegister.pointOfSale', 'user', 'transactions', 'discrepancies'])
                ->findOrFail($sessionId);

            if ($isManager && optional($session->cashRegister)->point_of_sale_id !== $user->point_of_sale_id) {
                abort(403, 'Cette session n\'appartient pas à votre point de vente.');
            }

            if ($session->is_closed && !($isAdmin || $isManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le billetage ne peut être imprimé que pour une session Férmée.'
                ], Response::HTTP_CONFLICT);
            }
            $printerConfig = PrinterModel::where('cash_register_id', $session->cash_register_id)
                ->orderByDesc('is_default')
                ->first();

            if (!$printerConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune imprimante configurée pour cette caisse.'
                ], 400);
            }

            $service = new CashRegisterSessionSummaryService();
            $summary = $service->build($session);

            $connector = $this->getConnector($printerConfig);
            $printer = new Printer($connector);

            try {
                $this->printSessionRecapTicket($printer, $session, $summary, $user);
                $printer->cut();
                $printer->close();
            } catch (\Throwable $throwable) {
                try { $printer->close(); } catch (\Throwable $e) {}
                throw $throwable;
            }

            return response()->json([
                'success' => true,
                'message' => 'Récapitulatif de session imprimé avec succès.'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur impression recap session: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur: '.$e->getMessage()
            ], 500);
        }
    }

    private function printSessionRecapTicket(Printer $printer, CashRegisterSession $session, array $summary, $user): void
    {
        $maxChars = 42;
        $line = str_repeat('-', $maxChars);
        $formatCurrency = fn($amount) => number_format((float) $amount, 0, ' ', ' ') . ' Ar';
        $printer->textChinese('');
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text("RECAP SESSION #".$session->id."\n");
        $printer->setEmphasis(false);

        $openedAt = $session->opened_at ? Carbon::parse($session->opened_at) : now();
        $printer->text($openedAt->format('d/m/Y H:i'));
        $printer->text("\n");
        if ($session->cashRegister) {
            $printer->text('Caisse: '.$session->cashRegister->name."\n");
        }
        if ($user) {
            $printer->text('Imprimé par: '.$user->name."\n");
        }
        $printer->feed(1);

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($line."\n");

        $cashSales = $summary['cash_sales_total'] ?? 0;
        $startingCash = $summary['start_cash_amount'] ?? ($session->starting_amount ?? 0);
        $cashWithFloat = $summary['cash_with_float'] ?? ($cashSales + $startingCash);
        $actualCash = $summary['actual_cash_amount'] ?? $summary['session']['actual_cash_amount'] ?? $session->actual_cash_amount ?? 0;
        $difference = $actualCash - $cashSales;

        $printer->setEmphasis(true);
        $printer->text("MONTANTS ESPECES\n");
        $printer->setEmphasis(false);
        $printer->text($this->padLine('Fond de caisse', $formatCurrency($startingCash), $maxChars) . "\n");
        $printer->text($this->padLine('Ventes espèces', $formatCurrency($cashSales), $maxChars) . "\n");
        $printer->setEmphasis(true);
        $printer->text($this->padLine('Espèces (fond inclus)', $formatCurrency($cashWithFloat), $maxChars) . "\n");
        $printer->setEmphasis(false);
        $printer->text($this->padLine('Comptage caisse', $formatCurrency($actualCash), $maxChars) . "\n");
        $printer->setEmphasis(true);
        $printer->text($this->padLine('Ecart', $formatCurrency($difference), $maxChars) . "\n");
        $printer->setEmphasis(false);

        $printer->text($line."\n");
        $printer->text("VENTES PAR CATEGORIE\n");

        foreach ($summary['categories'] as $category) {
            $printer->setEmphasis(true);
            $printer->text(strtoupper($category['category_name'])."\n");
            $printer->setEmphasis(false);
            $headerLeft = sprintf("  %-21s %6s", 'Produit', 'Qté');
            $printer->text($this->padLine($headerLeft, 'Montant', $maxChars) . "\n");
            $underlineLeft = sprintf("  %-20s %6s", str_repeat('-', 20), str_repeat('-', 3));
            $printer->text($this->padLine($underlineLeft, str_repeat('-', 8), $maxChars) . "\n");

            foreach ($category['products'] as $product) {
                $name = substr($product['product_name'], 0, 20);
                $quantity = number_format($product['quantity'], 0, ',', ' ');
                $amount = $formatCurrency($product['amount']);
                $leftPart = sprintf("  %-20s %6s", $name, str_pad($quantity, 6, ' ', STR_PAD_LEFT));
                $printer->text($this->padLine($leftPart, $amount, $maxChars) . "\n");
            }

            $printer->setEmphasis(true);
            $totalLeft = sprintf("  %-20s %6s", 'Total', '');
            $printer->text($this->padLine($totalLeft, $formatCurrency($category['amount']), $maxChars) . "\n");
            $printer->setEmphasis(false);
            $printer->text("\n");
        }

        $printer->text($line."\n");
        $printer->text("PAIEMENTS\n");

        $totalPayments = 0;
        foreach ($summary['payments'] as $payment) {
            $totalPayments += (float) ($payment['total'] ?? 0);
            $left = substr($payment['payment_name'] ?? 'N/A', 0, 28);
            $printer->text($this->padLine($left, $formatCurrency($payment['total'] ?? 0), $maxChars) . "\n");
        }

        $printer->setEmphasis(true);
        $printer->text($this->padLine('TOTAL PAIEMENTS', $formatCurrency($totalPayments), $maxChars) . "\n");
        $printer->setEmphasis(false);

        $printer->text($line."\n");
        $printer->feed(2);
    }

    private function padLine(string $left, string $right, int $width): string
    {
        $rightWidth = strlen($right);
        $availableLeft = max($width - $rightWidth, 0);
        $leftTrimmed = substr($left, 0, $availableLeft);
        $leftPart = str_pad($leftTrimmed, $availableLeft, ' ', STR_PAD_RIGHT);
        return $leftPart . $right;
    }

    private function printMainInvoice(Printer $printer, $order, $user, $cashRegister, string $logoPath): void
    {
        $maxChars = 45;
        $center = fn($text) => strlen($text) < $maxChars ? str_repeat(' ', floor(($maxChars - strlen($text)) / 2)) . $text : $text;
        $right = fn($text) => strlen($text) < $maxChars ? str_repeat(' ', $maxChars - strlen($text)) . $text : $text;

        if (file_exists($logoPath)) {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->getPrintConnector()->write(LogoPrinter::imageToEscPos($logoPath));
            $printer->feed(1);
        }

        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $printer->setEmphasis(true);
        $printer->text($center('TICKET #' . $order->ticket_number) . "\n");
        $printer->text($center('Date: ' . $order->created_at->format('d/m/Y H:i')) . "\n");
        $printer->text($center('Caissier: ' . $user->name) . "\n");
        $printer->setEmphasis(false);

        if ($user->pointOfSale && $cashRegister->cashRegister) {
            $printer->text($center('POS: ' . $user->pointOfSale->name . ' | Caisse: ' . $cashRegister->cashRegister->name) . "\n");
        }

        $printer->text(str_repeat('-', $maxChars) . "\n");

        foreach($order->orderLines as $item){
            if (!$item->product) {
                continue;
            }

            $productName = substr($item->product->name, 0, 20);
            $quantity = $item->quantity;
            $price = number_format($item->price, 0, ',', ' ');
            $total = number_format($item->price * $item->quantity, 0, ',', ' ');

            $printer->text(sprintf(
                "%-20s %2d x %8s = %6s\n",
                $productName,
                $quantity,
                $price,
                $total
            ));
        }

        $printer->text(str_repeat('-', $maxChars) . "\n");

        $netToPayValue = $order->final_amount ?? $order->total_amount;
        $netToPayFormatted = number_format($netToPayValue, 0, ',', ' ');
        $amountReceivedValue = $order->amount_received ?? null;
        $changeValue = $order->change_amount ?? null;

        if ($amountReceivedValue !== null) {
            $amountReceivedValue = (float) $amountReceivedValue;
        }

        if ($changeValue !== null) {
            $changeValue = (float) $changeValue;
        }

        if ($amountReceivedValue === null && $changeValue !== null) {
            $amountReceivedValue = $netToPayValue + $changeValue;
        }

        if ($changeValue === null && $amountReceivedValue !== null) {
            $changeValue = $amountReceivedValue - $netToPayValue;
        }

        $discountAmount = max($order->total_amount - $netToPayValue, 0);
        if ($discountAmount > 0) {
            $printer->text($right('REMISE: ' . number_format($discountAmount, 0, ',', ' ') . ' Ar') . "\n");
        }

        $printer->text($right('NET A PAYER: ' . $netToPayFormatted . ' Ar') . "\n");

        if ($amountReceivedValue !== null) {
            $amountReceivedFormatted = number_format($amountReceivedValue, 0, ',', ' ');
            $printer->text($right('MONTANT RECU: ' . $amountReceivedFormatted . ' Ar') . "\n");
        }

        $changeFormatted = number_format(max($changeValue ?? 0, 0), 0, ',', ' ');
        $printer->text($right('MONTANT RENDU: ' . $changeFormatted . ' Ar') . "\n");

        $printer->text(str_repeat('-', $maxChars) . "\n");

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Merci pour votre visite !\n");
        $printer->feed(2);
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $printer->cut();

        Log::info('Ticket principal imprimé pour la commande ' . $order->id);
    }

    private function printCategoryOrder(PrinterModel $printerConfig, $printerType, $items, $order, $user, $cashRegister, $logoPath)
    {
        $printer = null;
        try {
            $connector = $this->getConnector($printerConfig);
            $printer = new Printer($connector);
            $printer->textChinese();


            $maxChars = 42;
            $divider = str_repeat('-', $maxChars);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setTextSize(2, 2);
            $printer->text('COMMANDE À PRÉPARER' . "\n");
            $printer->setTextSize(1, 1);

            $printer->text(str_repeat('=', $maxChars) . "\n");
            $printer->setTextSize(2, 2);
            $printer->text('TICKET # ' . $order->ticket_number . "\n");
            $printer->setTextSize(1, 1);

            if ($order->table) {
                $printer->text('Table: ' . $order->table->table_number . "\n");
            }
            $printer->text('Date: ' . $order->created_at->format('d/m/Y H:i') . "\n");
            if ($user) {
                $printer->text('Caissier: ' . $user->name) . "\n";
            }
            if ($user->pointOfSale && $cashRegister->cashRegister) {
                $printer->text('Point de vente: ' . $user->pointOfSale->name . ' - Caisse: ' . $cashRegister->cashRegister->name . "\n");
            }

            $printer->text($divider . "\n");

            $this->printItemsByCategory($printer, $items, $maxChars);

            $printer->text($divider . "\n");
            $printer->feed(1);

            $printer->cut();
            $printer->close();

            Log::info("Commande $printerType imprimée pour la commande " . $order->id);
        } catch (\Exception $e) {
            if ($printer) {
                $printer->close();
            }
            Log::error("Erreur impression $printerType : " . $e->getMessage());
        }
    }

    private function groupOrderLinesByPrinterType($order): array
    {
        $types = [];
        foreach($order->orderLines as $line){
            $type = $line->product?->category?->printerType?->name;
            if($type) {
                $types[$type][] = $line;
            }
        }
        return $types;
    }

    private function printCashTicket(Printer $printer, $items, $order, $user, $cashRegister, string $logoPath): void
    {
        $maxChars = 42;
        $divider = str_repeat('-', $maxChars);
        $printer->textChinese();

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setTextSize(2, 2);
        $printer->text('COMMANDE' . "\n");
        $printer->text('TICKET # ' . $order->ticket_number . "\n");
        $printer->setTextSize(1, 1);
        $printer->text(str_repeat('=', $maxChars) . "\n");
        if ($order->table) {
            $printer->text('Table: ' . $order->table->table_number . "\n");
        }
        $printer->text('Date: ' . $order->created_at->format('d/m/Y H:i') . "\n");
        if ($user) {
            $printer->text('Caissier: ' . $user->name) . "\n";
        }
        if ($user->pointOfSale && $cashRegister->cashRegister) {
            $printer->text('Point de vente: ' . $user->pointOfSale->name . ' - Caisse: ' . $cashRegister->cashRegister->name . "\n");
        }

        $printer->text($divider . "\n");

        $this->printItemsByCategory($printer, $items, $maxChars);

        $printer->text($divider . "\n");


        $printer->feed(1);
        $printer->cut();
    }

    private function printItemsByCategory(Printer $printer, $items, int $maxChars): void
    {
        $itemsByCategory = [];
        foreach($items as $item){
            if (!$item->product) {
                continue;
            }
            $categoryName = $item->product->category?->name ?? 'Sans catégorie';
            $itemsByCategory[$categoryName][] = $item;
        }

        $lastCategoryKey = array_key_last($itemsByCategory);
        $labelWidth = max($maxChars - 7, 20);

        foreach($itemsByCategory as $categoryName => $categoryItems){
            $printer->setEmphasis(true);
            $printer->text(strtoupper($categoryName) . "\n");
            $printer->setEmphasis(false);

            foreach($categoryItems as $categoryItem){
                if (!$categoryItem->product) {
                    continue;
                }

                $productName = strtoupper(substr($categoryItem->product->name, 0, $labelWidth));
                $quantity = 'x' . (int) $categoryItem->quantity;
                $printer->text(sprintf("%-" . $labelWidth . "s %6s\n", $productName, $quantity));
            }

            if ($categoryName !== $lastCategoryKey) {
                $printer->text(str_repeat('-', $maxChars) . "\n\n");
            }
        }
    }

    private function printPaymentSummary(Printer $printer, $order, callable $center): void
    {
        $netToPayValue = $order->final_amount ?? $order->total_amount;
        $amountReceivedValue = $order->amount_received ?? null;
        $changeValue = $order->change_amount ?? null;

        if ($amountReceivedValue !== null) {
            $amountReceivedValue = (float) $amountReceivedValue;
        }

        if ($changeValue !== null) {
            $changeValue = (float) $changeValue;
        }

        if ($amountReceivedValue === null && $changeValue !== null) {
            $amountReceivedValue = $netToPayValue + $changeValue;
        }

        if ($changeValue === null && $amountReceivedValue !== null) {
            $changeValue = $amountReceivedValue - $netToPayValue;
        }

        if ($amountReceivedValue !== null) {
            $printer->text($center('MONTANT RECU: ' . number_format($amountReceivedValue, 0, ',', ' ') . ' Ar') . "\n");
        }

        if ($changeValue !== null && $changeValue > 0) {
            $printer->text($center('MONTANT RENDU: ' . number_format($changeValue, 0, ',', ' ') . ' Ar') . "\n");
        }
    }

    private function normalizeConnectionFields(array $data, ?PrinterModel $existing = null): array
    {
        $type = $data['connection_type'] ?? $existing?->connection_type ?? 'network';
        $type = $type ?: 'network';
        $data['connection_type'] = $type;

        if ($type === 'network') {
            $ip = $data['ip_address'] ?? $existing?->ip_address;
            if (!$ip) {
                throw ValidationException::withMessages([
                    'ip_address' => 'Adresse IP requise pour une imprimante réseau.'
                ]);
            }
            $data['ip_address'] = $ip;
            $data['port'] = $data['port'] ?? $existing?->port ?? 9100;
            $data['usb_identifier'] = null;
        } elseif ($type === 'usb') {
            $identifier = $data['usb_identifier'] ?? $existing?->usb_identifier;
            if (!$identifier) {
                throw ValidationException::withMessages([
                    'usb_identifier' => 'Le chemin ou identifiant USB est requis pour une imprimante USB.'
                ]);
            }
            $data['usb_identifier'] = $identifier;
            $data['ip_address'] = null;
            $data['port'] = null;
        } else {
            $data['ip_address'] = null;
            $data['port'] = null;
            $data['usb_identifier'] = null;
        }

        return $data;
    }
}
