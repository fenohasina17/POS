# Features & Domain Documentation

## Overview
A Laravel-based POS system for retail and restaurant environments. It employs a service-oriented architecture to handle complex business rules, separating concerns between API orchestration (Controllers) and business logic (Services).

## Domain Boundaries & Responsibilities

### 1. Sales & Order Domain (`app/Services/SaleService.php`)
- **Responsibility**: Orchestrates the sale lifecycle.
- **Retail**: Atomic "direct" sales.
- **Restaurant**: Stateful orders. Manages transition from `pending` (adding items) to `paid` (finalized sale).
- **Ticket Generation**: Atomic, session-scoped incremental numbering using `lockForUpdate()`.
- **Payment Validation**: Supports multiple payments with a 0.01 Ar tolerance for insufficiency checks.
- **Patterns Used**: Strategy pattern for payment processing.

### 2. Product & Inventory Domain (`app/Models/Product.php`)
- **Responsibility**: Manages catalog structure and pricing.
- **Grouping**: Uses `Category` to define production zones.
- **Routing**: `PrintGroupingService` acts as a specialized Factory/Builder to route `OrderLines` to printers based on `Category` configuration.

### 3. Financial/Cash Domain (`app/Services/CashRegisterSessionSummaryService.php`)
- **Responsibility**: Reconciliation, shift tracking, and audit logging.
- **Transaction Flow**: Immutable logging of all cash movements (`CashTransaction`). Cash transactions are automatically generated for 'Espèce' payments.
- **Billetage**: Guided cash count using standard Madagascar denominations (20k, 10k, 5k, 2k, 1k, 500, 200, 100 Ar).
- **Discrepancy**: Detects and logs deviations between system-expected and user-declared cash.

### 4. Restaurant/Operational Domain (`app/Models/Table.php`)
- **Responsibility**: Spatial management of the POS environment.
- **Concurrency**: Implements session-based locking mechanisms.
- **Auto-Unlock**: 1-minute inactivity timeout for table locks if no active sale is detected.

### 5. Reporting & Analytics Domain (`app/Http/Controllers/SalesExportController.php`)
- **CSV Export**: Multi-filter export system (POS, Product, custom Date ranges, Week/Month/Year).
- **KPI Engine**: Product-level performance tracking (revenue vs. quantity) per Point of Sale.
- **Monthly Insights**: Automated calculation of average ticket size and cash-to-total ratios.

### 6. Frontend Application Layer (`src/composables`)
- **Reactive Cart**: Logic-heavy cart management with real-time total calculation and discount handling.
- **Session-Guarded UI**: Dynamic disabling of sales features when no cash session is active or when billetage is required.
- **Composable Architecture**: Business logic isolation (Sales, Sessions, Printing) using Vue 3 Composables.

### 7. Real-time & Desktop Layer (`src/services/echo.js`)
- **Websocket Sync**: Instant UI updates for table status, kitchen orders, and session states via Laravel Echo.
- **Electron Native Bridge**: IPC handlers for direct thermal printing (ESC/POS) and local printer discovery.
- **Printer Routing**: Client-side logic to route specific order lines to different physical printers based on product categories.

## Technical Patterns & Architectural Notes
...
### Side Effects & Observers
- **Table Cleanup**: `SaleObserver` automatically frees tables upon sale deletion.
- **Real-time Updates**: Broadcasters (`TableLockUpdated`, `CashRegisterSessionOpened`) maintain UI state across all Electron clients.

## Key Workflows (Actionable Logic)

### The Retail Path
1. **Init**: Open `CashRegisterSession`.
2. **Process**: Build `Sale` -> Add `OrderLines` -> Apply `Pricing`.
3. **Checkout**: Apply `SalePayments` -> Close `Sale`.

### The Restaurant Path
1. **Lock**: `Table` -> `lock()` (prevents other sessions).
2. **Interact**: Add items to `Sale` (status: `pending`).
3. **Kitchen**: `PrintGroupingService` creates tickets based on category.
4. **Finalize**: Apply payments -> `unlock()` `Table`.

### The Closing Path
1. **Declaration**: User enters actual cash count.
2. **Reconciliation**: `CashRegisterSessionSummaryService` computes `Expected` (sales - refunds +/in/out).
3. **Audit**: If `Expected != Actual`, create `SessionDiscrepancy`.
4. **Finalize**: Set `CashRegisterSession` to closed.
