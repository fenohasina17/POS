# Project: POS Fullstack (Laravel + Vue + Electron)

## Overview
This is a Laravel-based Point of Sale (POS) system with a Vue 3 frontend wrapped in Electron for native hardware support.

## Architecture
### Backend (Laravel)
- **MVC Pattern:** Standard Laravel structure.
- **Service Layer:** Business logic MUST be encapsulated in service classes located in `app/Services`. Controllers in `app/Http/Controllers` should remain thin and responsible only for handling requests/responses.
- **Models:** Eloquent Models in `app/Models` should contain relationships and accessors/mutators.

### Frontend (Vue 3 + Electron)
- **Composition API:** Use script setup and composables for business logic and state.
- **State Management:** Use Pinia for global state (Auth, POS, Cart).
- **Electron Integration:** Use Electron IPC (`window.electronAPI`) for thermal printing and native features.
- **Real-time:** Integrate Laravel Echo with Reverb for live updates (Table status, orders).

## Design Principles & Patterns
...
## Coding Conventions
### Backend (PHP)
- **Style:** Adhere strictly to PSR-12.
- **Typing:** Use strict typing (`declare(strict_types=1);`) in all PHP files.
- **Documentation:** Public methods must include PHPDoc blocks explaining parameters, return types, and side effects.

### Frontend (Vue/JS)
- **Modularity:** Encapsulate reusable logic in composables (`src/composables`).
- **Styling:** Use Tailwind CSS for utility-first styling.
- **Naming:** Follow standard Vue conventions (PascalCase for components, camelCase for props/events).

## Testing
...
## Git & Workflow
...
## Security
- **Authentication:** Use Laravel Sanctum for API security.
- **Frontend Protection:** Use Vue Router guards to enforce authentication and active session requirements.
- **POS Isolation:** Ensure `X-Active-POS-ID` header is injected into all API requests via `apiClient` interceptors.
- **Sensitive Data:** Never commit `.env` or any file containing secrets/credentials.
