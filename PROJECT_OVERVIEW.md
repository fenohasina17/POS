# Project Overview – POS System (Laravel + Vue + Electron)

---

## 1️⃣ High‑level Architecture

```
+-------------------+      +-------------------+      +-------------------+
|   Laravel API     | <--->|   MySQL/Postgres  |<---->|   Laravel Eloquent |
| (backend)         |      |   (data store)    |      |   models & repos  |
+-------------------+      +-------------------+      +-------------------+
        ^  |                                   ^
        |  |                                   |
        |  v                                   |
+-------------------+      +-------------------+   |
|   Vue.js SPA      | <--->|   Vite dev server|   |
| (frontend)        |      +-------------------+   |
+-------------------+                               |
        ^  |                                         |
        |  |                                         |
        |  v                                         |
+-------------------+      +-------------------+          |
|   Electron shell  | <--> |   Node.js runtime |----------+
| (desktop client) |      +-------------------+
+-------------------+
```

- **Laravel** provides a RESTful API (authentication via Sanctum, policy‑based authorisation, extensive validation, transaction handling).
- **Vue 3** (Vite) builds an SPA that consumes the API, manages state with Pinia, and handles UI with TailwindCSS + Flowbite.
- **Electron** wraps the SPA, adds native printing via the `electron-pos-printer` library, and exposes IPC handlers for the backend.
- **Docker** is used for local development (php‑fpm, nginx, mysql, redis) and can be extended for production with multi‑stage images.
- **CI/CD** (Jenkinsfile) runs linting, tests (PHPUnit, Cypress), builds assets and produces Docker images.

---

## 2️⃣ Backend – Laravel (PHP 8.2+, Laravel 11.31)

### 2.1 Core Packages
- `barryvdh/laravel-dompdf` – PDF generation for receipts.
- `spatie/laravel-permission` – Role‑based permissions (`admin`, `gerant`, `caissier`).
- `laravel/reverb` – WebSocket broadcasting for real‑time UI updates.
- `mike42/escpos-php` – ESC/POS printer support (fallback for thermal printers).

### 2.2 Important Directories
| Directory | Purpose |
|-----------|---------|
| `app/Models` | Eloquent models (`Sale`, `OrderLine`, `Product`, `CashRegisterSession`, `User`, etc.) |
| `app/Http/Controllers` | API controllers (`SaleController`, `ProductController`, `PrinterController`, …) |
| `app/Services` | Business logic (`SaleService`, `PrintGroupingService`, `CashTransactionService`) |
| `routes/api.php` (not shown) | API route definitions – all endpoints are prefixed with `/api/` and protected by `auth:sanctum` |
| `database/migrations` | Schema definitions – `sales`, `order_lines`, `payments`, `cash_register_sessions`, `point_of_sales`, … |
| `config/` | Laravel config files – `app.php`, `auth.php`, `permission.php`, `reverb.php` |

### 2.3 Key API Endpoints (excerpt)
| Method | URL | Description |
|--------|-----|-------------|
| `GET` | `/api/sales` | List sales with filters (POS, session, user, status). |
| `POST` | `/api/sales` | Create a new sale (supports single or multiple payments). |
| `POST` | `/api/sales/{sale}/validate` | Validate a pending order and lock the table. |
| `GET` | `/api/point-of-sales/{pos}/kpis` | Product‑wise KPI (quantity, revenue). |
| `GET` | `/api/sales/monthly/{pos}` | Monthly and daily breakdown of sales. |
| `GET` | `/api/printers` | List system printers (used by Electron). |
| `POST` | `/api/print` | Trigger a receipt/bon via Electron IPC (handled in `main.cjs`). |

---

## 3️⃣ Frontend – Vue 3 + Vite

### 3.1 Entry point
- `frontend/main.cjs` – creates the Electron `BrowserWindow`, loads `http://localhost:5173` in dev or the built `dist/index.html` in production.

### 3.2 UI Stack
- **TailwindCSS** (`tailwind.config.js`) – utility‑first styling.
- **Flowbite** – component library (modals, dropdowns, tables).
- **FontAwesome** – icons for menus and printer status.
- **Pinia** stores (`stores/`) – global state for auth, POS selection, cart, printing queues.
- **Vue‑Router** (`router/`) – routes: `/login`, `/dashboard`, `/sales`, `/kitchen`, `/reports`.

### 3.3 Key Vue Components (selected)
| Component | Path | Role |
|-----------|------|------|
| `App.vue` | root | Layout with `<router-view>` and global notification bar.
| `components/PrinterStatus.vue` | UI | Shows detected printers and connection health.
| `components/SaleForm.vue` | UI | Form to create a sale, handles payment selection (single/multiple). |
| `components/ReceiptPreview.vue` | UI | Renders HTML receipt using the same template as Electron (`generateHTML`). |
| `views/Dashboard.vue` | page | KPI charts (monthly sales, product performance). |
| `views/Kitchen.vue` | page | Real‑time order list, uses Reverb websockets. |

---

## 4️⃣ Electron Integration

- **Main process** (`frontend/main.cjs`) sets up IPC handlers (`print-receipt`, `print-order`, `print-session-summary`).
- **Printing logic** (`frontend/main.cjs` > `handlePrint`) builds an HTML receipt using the same style functions (`getPrintStyles`, `generateHTML`).
- **Printer discovery** – `ipcMain.handle('get-printers')` returns `webContents.getPrintersAsync()`; fallback to a “receipt” printer (XP‑80C or default Windows printer).
- **Security** – `contextIsolation: true`, `nodeIntegration: false`, preload script (`preload.cjs`) exposes safe APIs to the renderer.

---

## 5️⃣ Docker & Development Environment

### 5.1 Docker‑compose (root `docker-compose.yml`)
- Services: `php-fpm`, `nginx`, `mysql`, `redis`, `mailhog`.
- Volumes mount the source code (backend & frontend) for live reload.
- `frontend` runs Vite dev server on port 5173; Electron loads that URL.

### 5.2 Production Dockerfile (backend) – example steps
1. **Builder stage** – `composer install --no-dev --optimize-autoloader`.
2. **PHP‑FPM image** – copy built vendor and app files, set `APP_ENV=production`.
3. **Nginx stage** – serve the compiled Vue SPA from `/public`.
4. **Multi‑stage** – final image size ~150 MB.

---

## 6️⃣ CI / CD (Jenkins)
- **Lint** – `phpcs`, `phpstan`, `eslint`, `stylelint`.
- **Tests** – `phpunit` (backend), `cypress` (frontend UI).
- **Build** – `npm run build` for Vue, then Docker image build.
- **Deploy** – push image to registry, pull on target server, run `docker‑compose up -d`.
- The pipeline is defined in `Jenkinsfile`; it can be converted to GitHub Actions if desired.

---

## 7️⃣ Testing Strategy
- **Backend** – unit tests for services (`SaleServiceTest`, `PrintGroupingServiceTest`), feature tests for API endpoints (`SaleApiTest`).
- **Frontend** – Cypress end‑to‑end covering login, order creation, receipt preview, and real‑time kitchen updates.
- **Electron** – integration tests (via Spectron or Playwright) to verify IPC calls and printer fallback.

---

## 8️⃣ Configuration & Environment
- `.env.example` contains keys: `APP_KEY`, `DB_CONNECTION`, `DB_DATABASE`, `CACHE_DRIVER`, `QUEUE_CONNECTION`, `SANCTUM_STATEFUL_DOMAINS`, `ECHO_HOST`, `ECHO_PORT`.
- **Important variables**:
  - `POINT_OF_SALE_ID` – set by middleware to define the active POS for non‑admin users.
  - `ECHO_HOST`/`ECHO_PORT` – Reverb WebSocket server.
  - `PRINT_DEFAULT` – optional printer name used by Electron when no explicit printer is supplied.

---

## 9️⃣ Future Improvements (non‑exhaustive)
- Upgrade Laravel to **12.x** (once all packages support it).
- Migrate Vue to **Vite 5** / enable **ES modules** for better tree‑shaking.
- Replace `electron-pos-printer` with a pure‑JS printing library for broader OS support.
- Introduce **GitHub Actions** for CI to reduce Jenkins maintenance.
- Add **OpenAPI/Swagger** documentation for the API (currently only `API_DOCUMENTATION.md`).

---

## 🔟 Detailed Workflows & Logic
For a deep dive into the cash counting (Billetage), session management, and order validation workflows, please refer to:
👉 **[Business Logic & Workflows](file:///C:/Users/Ben/Documents/proz/POS/docs/business_logic.md)**

---

*This document provides a concise yet comprehensive view of the POS project’s structure, technology choices, and critical components. It can be expanded with diagrams (UML, component) or deeper API specs as needed.*
