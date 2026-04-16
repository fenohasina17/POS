# API Documentation — POS-B

Ce document décrit les endpoints principaux exposés par l'API REST (routes définies dans routes/api.php).
Exemples d'usage en `curl`. Les routes protégées nécessitent un header `Authorization: Bearer <token>` obtenu via `/login`.

**Authentification**
- POST /login
  - Description: authentifie un utilisateur.
  - Payload: { "email": "user@example.com", "password": "password" }
  - Réponse: { "token": "...", "user": { ... } }
  - Exemple:
    ```bash
    curl -X POST "http://localhost/api/login" -H "Content-Type: application/json" -d '{"email":"admin@example.com","password":"secret"}'
    ```

- POST /register
  - Crée un utilisateur.

- POST /logout (auth)
  - Invalide le token courant.

- GET /me (auth)
  - Récupère l'utilisateur connecté.

**Users** (resource)
- GET /users (auth)
- POST /users (auth)
- GET /users/{id} (auth)
- PUT/PATCH /users/{id} (auth)
- DELETE /users/{id} (auth)

Exemple création:
```bash
curl -X POST http://localhost/api/users \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"name":"Joe","email":"joe@example.com","password":"password"}'
```

**PointOfSale**
- GET /pointofsales (auth)
- POST /pointofsales (auth)
- GET /pointofsales/{id} (auth)
- PUT/PATCH /pointofsales/{id} (auth)
- DELETE /pointofsales/{id} (auth)
- GET /products/pointofsale/{id} — retourne produits + prix pour le POS spécifié

**Categories**
- Resource standard: /categories (auth)
- Paramètres utiles: `with_products`, `with_pricing`, `point_of_sale_id`

**Products**
- Resource standard: /products (auth)

**Pricings**
- Resource standard: /pricings (auth)
- GET /pricings — retourne pricings pour le point de vente de l'utilisateur connecté
- GET /pricings/{productId} — pricing d'un produit pour le POS de l'utilisateur

**Sales (Ventes & commandes en attente)**
- Resource standard: /sales (auth)
- GET /sales/current-session — ventes pour la session donnée (`cash_register_session_id` query)
- POST /sales — créer vente (par défaut status `pending` si utilisé pour commandes)
- POST /sales/pending-order — créer une commande en attente (table)
- POST /sales/{saleId}/add-products — ajouter produits à commande en attente
- POST /sales/{saleId}/remove-products — retirer lignes
- POST /sales/{saleId}/validate — valider/terminer la commande (paiement)
- POST /sales/{saleId}/print/{printerId} — imprimer une vente finalisée
- POST /sales/{saleId}/print-pending/{printerId} — imprimer une commande en attente

Exemple valider commande:
```bash
curl -X POST http://localhost/api/sales/123/validate \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"payment_id":1}'
```

**OrderLines**
- Resource standard: /orderlines (auth)

**Payments**
- Resource standard: /payments (auth)

**Cash Registers**
- Resource: /cash-registers (auth)
- GET /cash-registers/client-ip — retourne IP client
- GET /cash-registers/{cashRegister}/current-session — session courante
- POST /cash-registers/{cashRegisterId}/printers/{printerId}/sales/{saleId}/print — print via caisse

**Cash Register Sessions**
- Resource: /cash-register-sessions (auth)
- POST /cash-register-sessions — ouvrir session
- PATCH/PUT /cash-register-sessions/{id} — mettre à jour / fermer (is_closed + actual_cash_amount + closed_at)
- GET /cash-register-sessions/{id}/summary — récapitulatif (nécessite session fermée)
- POST /cash-register-sessions/{id}/reopen — rouvrir
- GET /cash-register-session/my-active-session — session active de l'utilisateur
- GET /cash-register-sessions/{id}/discrepancies — lister écarts
- POST /cash-register-sessions/{id}/discrepancies — ajouter écart
- GET /cash-registers-sessions/{id}/status — obtenir status (open/available)

**Cash Transactions**
- Resource: /cash-transactions (auth)
- POST /cash-transactions — créer transaction (met à jour `expected_cash_amount` automatiquement)

**Printers**
- Resource: /printers (auth, avec permissions pour certaines actions)
- GET /printer-scan — détecte imprimantes locales via service
- POST /printers/{printer}/test — tester imprimante
- POST /printers/invoice/{orderId} — imprimer facture (autorisation `print.invoice` requise)
- POST /printers/session-recap/{sessionId} — imprimer récap session
- GET /printers/sales-to-print — ventes en attente d'impression

**Printer Types**
- Resource: /printer-types (auth)

**Tables**
- Resource: /tables (auth)
- GET /tables/available
- GET /tables/occupied
- PATCH /tables/{id}/status — changer statut (available, occupied, reserved, out_of_order)
- GET /tables/statistics
- GET /tables/{tableId}/pending-orders — commandes en attente pour la table

**Roles & Permissions (Spatie)**
- Roles: /roles (apiResource)
- Permissions: /permissions (apiResource except update)
- Assign/Revoke permissions to role: POST /roles/{role}/permissions, DELETE /roles/{role}/permissions/{permission}
- User roles: GET|POST|DELETE under /users/{user}/roles
- User permissions: GET /users/{user}/permissions, GET /users/{user}/permissions/{permission}/check

---

Fichiers sources pertinents:
- Routes: [routes/api.php](routes/api.php)
- Contrôleurs principaux: [app/Http/Controllers/SaleController.php](app/Http/Controllers/SaleController.php), [app/Http/Controllers/PrinterController.php](app/Http/Controllers/PrinterController.php), [app/Http/Controllers/CashRegisterSessionController.php](app/Http/Controllers/CashRegisterSessionController.php)

Voulez-vous que je :
- génère une collection Postman / OpenAPI (Swagger) automatiquement à partir des routes et contrôleurs ?
- ajoute exemples de requêtes/réponses plus détaillés pour chaque endpoint dans ce fichier ?

