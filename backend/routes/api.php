<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PointOfSaleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\OrderLineController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CashRegisterSessionController;
use App\Http\Controllers\CashTransactionController;
use App\Http\Controllers\SalePaymentController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserRoleController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\SessionDiscrepancyController;
use App\Http\Controllers\SalesExportController;

// Endpoint de santé (non authentifié — utilisé par monitoring)
Route::get('/health', function () {
    try {
        \DB::connection()->getPdo();
        $dbStatus = 'ok';
    } catch (\Exception $e) {
        $dbStatus = 'error';
    }
    try {
        \Cache::store('redis')->set('health_check', '1', 5);
        $redisStatus = 'ok';
    } catch (\Exception $e) {
        $redisStatus = 'error';
    }
    $status = ($dbStatus === 'ok' && $redisStatus === 'ok') ? 'healthy' : 'degraded';
    $code   = $status === 'healthy' ? 200 : 503;
    return response()->json([
        'status'    => $status,
        'db'        => $dbStatus,
        'redis'     => $redisStatus,
        'timestamp' => now()->toISOString(),
        'version'   => config('app.name'),
    ], $code);
})->name('health');

// Routes publiques (non authentifiées)
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register']);

Route::get('/logo', function () {
    $path = public_path('photos/logo.png');
    if (!file_exists($path)) {
        return response()->json(['error' => 'Logo not found'], 404);
    }
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);
    return base64_encode($data);
});

// Toutes les routes protégées par Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // ========== AUTHENTIFICATION ==========
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ========== UTILISATEURS ==========
    Route::apiResource('users', UserController::class);

    // ========== POINTS DE VENTE ==========
    Route::apiResource('point_of_sales', PointOfSaleController::class);
    // Detaché ou attché user sur pos
    Route::delete('/point-of-sales/{pointOfSale}/users/{user}', [PointOfSaleController::class, 'detachUser']);
    Route::post('/point-of-sales/{pointOfSale}/users/{user}', [PointOfSaleController::class, 'attachUser']);

    Route::prefix('point-of-sales')->group(function () {
        Route::apiResource('', PointOfSaleController::class)->names('point-of-sales');
        Route::get('/{pointOfSale}/monthly-sales', [SaleController::class, 'monthlySales'])
            ->name('point-of-sales.monthly-sales');
        Route::get('/{pointOfSale}/product-kpis', [SaleController::class, 'productKpis'])
            ->name('point-of-sales.product-kpis');
    });
    Route::get('/products/pointofsale/{id}', [PointOfSaleController::class, 'getProductsByPointOfSale']);

    // ========== CATÉGORIES ET PRODUITS ==========
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('pricings', PricingController::class);
    // ========== PRODUITS PAR CATÉGORIE ==========
    Route::get('categories/{id}/products', [CategoryController::class, 'getProducts']);
    Route::get('categories/{id}/products-with-prices', [CategoryController::class, 'getProductsWithPrices']);

    // Routes pour les ventes de la session courante
    Route::get('/sales/current-session', [SaleController::class, 'getSalesForCurrentSession'])->name('sales.current-session');

    // ========== VENTES ==========
    Route::put('/sales/{sale}/order-lines', [SaleController::class, 'replaceOrderLines']);
    Route::get('/sales/export', [SalesExportController::class, 'export'])->name('sales.export'); // Added sales export route
    Route::apiResource('sales', SaleController::class);

    // Routes pour les commandes en attente
    Route::post('/sales/pending-order', [SaleController::class, 'createPendingOrder'])->name('sales.pending.create');
    Route::post('/sales/{saleId}/add-products', [SaleController::class, 'addToPendingOrder'])->name('sales.pending.add');
    Route::post('/sales/{saleId}/remove-products', [SaleController::class, 'removeFromPendingOrder'])->name('sales.pending.remove');
    Route::post('/sales/{saleId}/validate', [SaleController::class, 'validatePendingOrder'])->name('sales.pending.validate');
    Route::get('/tables/{tableId}/pending-orders', [SaleController::class, 'getPendingOrdersForTable'])->name('tables.pending.orders');

    // Routes pour l'annulation de vente
    Route::post('/sales/{saleId}/cancel', [SaleController::class, 'cancelSale'])->name('sales.cancel');

    // Route pour récupérer les données formatées par catégorie (sans PDF)
    Route::get('/sales/{saleId}/formatted', [SaleController::class, 'getFormattedSale'])->name('sales.formatted');
    Route::get('/sales/{saleId}/categories', [SaleController::class, 'getSaleCategories'])->name('sales.categories');

    // Routes pour les transactions cash
    Route::get('/sales/{saleId}/cash-transaction', [SaleController::class, 'getCashTransaction'])->name('sales.cash-transaction');
    Route::post('/sales/{saleId}/refund', [SaleController::class, 'refundSale'])->name('sales.refund');

    // ========== PAIEMENTS MULTIPLES ==========
    Route::prefix('sales/{sale}')->group(function () {
        Route::post('payments', [SalePaymentController::class, 'store'])->name('sales.payments.store');
        Route::get('payments', [SalePaymentController::class, 'index'])->name('sales.payments.index');
        Route::get('payments/{payment}', [SalePaymentController::class, 'show'])->name('sales.payments.show');
        Route::put('payments/{payment}', [SalePaymentController::class, 'update'])->name('sales.payments.update');
        Route::delete('payments/{payment}', [SalePaymentController::class, 'destroy'])->name('sales.payments.destroy');
    });

    // ========== LIGNES DE COMMANDE ==========
    Route::put('/sales/{sale}/order-lines', [SaleController::class, 'replaceOrderLines']);
    Route::apiResource('orderlines', OrderLineController::class);

    // ========== MODES DE PAIEMENT ==========
    Route::apiResource('payments', PaymentController::class);

    // ========== CAISSES ENREGISTREUSES ==========
    Route::prefix('cash-registers')->group(function () {
        Route::get('/client-ip', [CashRegisterController::class, 'clientIp'])->name('cash-registers.client-ip');
        Route::get('/{cashRegister}/current-session', [CashRegisterController::class, 'currentSession'])
            ->name('cash-registers.current-session');
    });
    Route::apiResource('cash-registers', CashRegisterController::class);

    // ========== SESSIONS DE CAISSE ==========
    Route::get('/cash-register-sessions/open', [CashRegisterSessionController::class, 'openSessions']);
    Route::apiResource('cash-register-sessions', CashRegisterSessionController::class);
    // Dans routes/api.php
    Route::get('/my-active-session', [CashRegisterSessionController::class, 'myActiveSession'])->name('my-active-session');
    Route::post('/cash-register-sessions/{id}/reopen', [CashRegisterSessionController::class, 'reopen'])->name('cash-register-sessions.reopen');
    Route::get('/cash-register-sessions/{id}/discrepancies', [CashRegisterSessionController::class, 'listDiscrepancies'])->name('cash-register-sessions.discrepancies.list');
    Route::post('/cash-register-sessions/{id}/discrepancies', [CashRegisterSessionController::class, 'addDiscrepancy'])->name('cash-register-sessions.discrepancies.add');
    Route::get('/cash-register-sessions/{id}/summary', [CashRegisterSessionController::class, 'summary'])->name('cash-register-sessions.summary');
    Route::get('/cash-register-sessions/status/{cashRegisterId}', [CashRegisterSessionController::class, 'status'])->name('cash-register-sessions.status');
    // Routes pour les statistiques des sessions
    Route::get('/cash-register-sessions/{id}/balance', [CashRegisterSessionController::class, 'getBalance'])
        ->name('cash-register-sessions.balance');
    Route::get('/cash-register-sessions/{id}/cash-transactions', [CashRegisterSessionController::class, 'getCashTransactions'])
        ->name('cash-register-sessions.cash-transactions');

    // ========== TRANSACTIONS CAISSE ==========
    Route::prefix('cash-transactions')->group(function () {
        Route::get('/', [CashTransactionController::class, 'index']);
        Route::post('/', [CashTransactionController::class, 'store']);
        Route::get('/{id}', [CashTransactionController::class, 'show']);
        Route::put('/{id}', [CashTransactionController::class, 'update']);
        Route::delete('/{id}', [CashTransactionController::class, 'destroy']);

        // Routes spécifiques
        Route::get('/session/{sessionId}', [CashTransactionController::class, 'getBySession'])
            ->name('cash-transactions.by-session');
        Route::get('/session/{sessionId}/balance', [CashTransactionController::class, 'getSessionBalance'])
            ->name('cash-transactions.session-balance');
        Route::get('/type/{type}', [CashTransactionController::class, 'getByType'])
            ->name('cash-transactions.by-type');
        Route::get('/date-range', [CashTransactionController::class, 'getByDateRange'])
            ->name('cash-transactions.by-date-range');
    });

    // ========== ÉCARTS DE CAISSE ==========
    Route::prefix('session-discrepancies')->group(function () {
        Route::get('/', [SessionDiscrepancyController::class, 'index']);
        Route::patch('/{id}/check', [SessionDiscrepancyController::class, 'check']);
    });

    // ========== TABLES ==========
    Route::apiResource('tables', TableController::class);
    Route::get('/tables/available', [TableController::class, 'getAvailableTables'])->name('tables.available');
    Route::get('/tables/occupied', [TableController::class, 'getOccupiedTables'])->name('tables.occupied');
    Route::patch('/tables/{id}/status', [TableController::class, 'updateStatus'])->name('tables.status.update');
    Route::get('/tables/statistics', [TableController::class, 'getStatistics'])->name('tables.statistics');

    // ========== GESTION DES RÔLES ET PERMISSIONS ==========
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permissions', PermissionController::class)->except(['update']);

    // Assignation / révocation permissions à un rôle
    Route::prefix('roles/{role}')->group(function () {
        Route::post('/permissions', [RolePermissionController::class, 'assign']);
        Route::delete('/permissions/{permission}', [RolePermissionController::class, 'revoke']);
    });

    // Rôles d'un utilisateur
    Route::prefix('users/{user}')->group(function () {
        Route::get('/roles', [UserRoleController::class, 'index']);
        Route::post('/roles', [UserRoleController::class, 'store']);
        Route::delete('/roles/{role}', [UserRoleController::class, 'destroy']);
    });

    // Permissions d'un utilisateur
    Route::prefix('users/{user}')->group(function () {
        Route::get('/permissions', [UserPermissionController::class, 'index']);
        Route::get('/permissions/{permission}/check', [UserPermissionController::class, 'check']);
    });
});
