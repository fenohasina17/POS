# Deep-Dive Workflow Analysis

## 1. Restaurant Table Locking Workflow (Atomic)
*   **Trigger**: `SaleController@createPendingOrder` (or adding items to an existing one).
*   **Lock Mechanism**: 
    - Verify `Table` status.
    - Check if `locked_by_session_id` is null or belongs to the current active session.
    - **Conflict**: Returns `409 Conflict` if locked by another session.
*   **Atomic Update**:
    - `Table::update(['locked_by_session_id' => $session->id, ...])`
    - Trigger `TableLockUpdated` event (via `event(...)`) for real-time UI updates (Websockets/Echo).
*   **Auto-Unlock Logic**: `TableController@index` performs a check for stale locks.
    - **Condition**: `locked_at` older than 1 minute AND no associated `pending`/`in_progress` sales.
*   **Unlock Trigger**:
    - `SaleController@validatePendingOrder` (payment finalized).
    - `SaleController@cancelSale` (order cancelled).
*   **Orphan Management**: `app/Console/Commands/CleanOrphanTableLocks.php` provides a cleanup mechanism to unlock tables if a session is closed abnormally.

## 2. Sale Lifecycle Workflow
...
## 3. Financial Reconciliation Workflow
...
## 4. Split & Partial Payment Workflow
*   **Context**: Handled by `SalePaymentService@addPayments`.
*   **Partial State**: Sale stays `pending` if `paid_amount < final_amount`.
*   **Discount Adjustment**: If a payment includes a `discount_percentage`, the sale's `final_amount` is recalculated mid-payment.
*   **Completion**: Atomically transitions to `completed` when the total threshold is met.

## 6. Multi-POS Pricing Workflow
...
## 7. Frontend Application Initialization Workflow
*   **Step 1: Auth**: User logs in; token and user data stored in Pinia/localStorage.
*   **Step 2: POS Selection**: If multiple POS are available, user selects one (stored in `posStore`).
*   **Step 3: Session Guard**: Route guard checks for an active `cash_register_session`.
    - If missing: Redirects to `/cash-printer` to open a session.
    - If present: Proceeds to `DirectSale` or `TableSale`.
*   **Step 4: Real-time Init**: Initialize `Laravel Echo` to listen for POS-specific events.

## 8. Real-time Synchronization Workflow (Websockets)
*   **Table Status**: Listen on `pos.{posId}` channel for `TableLockUpdated`. Update `TableLayout` UI instantly.
*   **Kitchen Orders**: Listen for new `Sale` events to refresh the Kitchen monitor in real-time.
*   **Session Updates**: Listen for `CashRegisterSessionOpened/Closed` to force UI re-validation or logout.

## 9. Hardware Integration Workflow (Electron)
*   **Discovery**: `get-printers` IPC call retrieves available system printers.
*   **Printing**: `print-pdf-receipt` or `print-pdf-order` IPC calls send formatted data to Electron's main process.
*   **Thermal Output**: Main process uses `electron-pos-printer` to render and send jobs to the target printer.

