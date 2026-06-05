# Business Logic & Workflows

This document explains the core business processes in the POS system: the Cash Register Session lifecycle (including cash counting / *Billetage*), and the Sales & Orders flow.

---

## 1. Cash Register Sessions (Sessions de Caisse)

A cash register session defines the period during which a cashier operates a specific physical/logical register. All sales made by the cashier are tied to this session.

### 1.1 Session Opening
- **Endpoint**: `POST /api/cash-register-sessions` (Handled by `CashRegisterSessionController@store`)
- **Logic**:
  - The cashier (not a manager) opens a session by specifying the `cash_register_id` and the `starting_amount` (fond de caisse).
  - The backend verifies that the cash register belongs to the cashier's active Point of Sale (`activePosId`).
  - A check ensures no other open session exists for that register.
  - The session is created with `is_closed = false`, and an event `CashRegisterSessionOpened` is broadcasted.

### 1.2 Billetage (Cash Counting)
*Billetage* is the process of counting physical cash at the end of the shift to verify against the system's expected total.
- **Frontend Component**: `Billetage.vue`
- **Logic**:
  - The cashier is presented with a grid of Malagasy Ariary denominations (20k, 10k, 5k, 2k, 1k, 500, 200, 100).
  - As the cashier inputs the quantities, the frontend calculates the `totalCounted`.
  - **Expected Amount Calculation**: The system computes `starting_amount + totalCashSales` (where `totalCashSales` only includes payments with the method name `'Espèce'`).
  - **Variance (Écart)**: The difference between `totalCounted` and the expected amount.
- **Validation**:
  - The cashier clicks "Valider le billetage".
  - **Endpoint**: `PUT /api/cash-register-sessions/{id}`
  - The system updates the session with `actual_cash_amount = totalCounted` and marks `is_bill_checked = true`.

### 1.3 Session Closure
- **Frontend**: Once the billetage is validated, the "Clôturer la session" button becomes available.
- **Logic**:
  - The closure request sets `is_closed = true` and `closed_at = now()`.
  - **Permission Note**: The frontend allows Managers (`gérant`) or Admins to click "Clôturer". However, backend rules in `CashRegisterSessionController@update` currently prevent non-admin Managers from updating a session, enforcing that the cashier (owner of the session) or an Admin must close it.
  - An event `CashRegisterSessionClosed` is dispatched.

---

## 2. Sales & Order Validation

Sales can be created as direct sales (immediate payment) or pending orders (e.g., table orders that are paid later).

### 2.1 Direct Sales
- **Endpoint**: `POST /api/sales` (Handled by `SaleController@store`)
- **Logic**:
  - Requires `total_amount`, `final_amount`, the products array (`items`), and either a single payment or an array of multiple `payments`.
  - Automatically calculates the `change_amount` (monnaie rendue) if not explicitly provided.
  - The sale is instantly marked as `completed`.
  - Transactions are recorded via `CashTransactionService` to keep the session's balance accurate.

### 2.2 Pending Orders & Table Management
- **Creation**: Orders mapped to a table (`table_id`) are often created with the status `pending`.
- **Validation (Payment)**:
  - **Endpoint**: `POST /api/sales/{saleId}/validate` (Handled by `SaleController@validatePendingOrder`)
  - **Logic**:
    - The server receives the payment details (e.g., `payment_id`, `amount_received`).
    - The `SaleService` transitions the sale status from `pending` to `completed`.
    - **Table Unlocking**: If the sale is attached to a table, the table is freed (`locked_by_session_id` and `locked_at` set to null). The `TableLockUpdated` event is broadcasted so all POS clients instantly see the table as available.
    - Optionally prepares a print job via `PrintGroupingService` to immediately print the receipt.

---

## 3. POS Context and Security Filtering

Throughout the backend (`SaleController`, `CashRegisterSessionController`), multi-tenant-like data isolation is enforced via the active Point of Sale.
- Users are assigned to one or more `PointOfSale` entities.
- Middleware injects an `activePosId` into the request attributes.
- **Queries and Actions**: Unless the user is an `admin`, all read queries (e.g., getting the list of sales or sessions) and write actions (e.g., creating a sale) are strictly filtered by `activePosId`. Users cannot view or modify data belonging to a different POS.
