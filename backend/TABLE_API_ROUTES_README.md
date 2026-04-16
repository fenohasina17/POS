# API Routes pour la Gestion des Tables

## Routes Resource de Base

Les routes resource suivantes ont été ajoutées pour la gestion complète des tables :

### `GET /api/tables`
- **Description** : Récupérer toutes les tables du point de vente de l'utilisateur connecté
- **Authentification** : Requise (Bearer token)
- **Paramètres** : Aucun
- **Réponse** : Liste des tables avec leurs relations (pointOfSale, sales)

### `POST /api/tables`
- **Description** : Créer une nouvelle table
- **Authentification** : Requise (Bearer token)
- **Paramètres** :
  ```json
  {
    "table_number": "string (requis, unique)",
    "name": "string (optionnel)",
    "capacity": "integer (requis, 1-50)",
    "description": "string (optionnel)",
    "location": "array (optionnel)"
  }
  ```
- **Réponse** : Table créée (201)

### `GET /api/tables/{id}`
- **Description** : Récupérer une table spécifique
- **Authentification** : Requise (Bearer token)
- **Paramètres** : `id` (ID de la table)
- **Réponse** : Détails de la table avec relations

### `PUT /api/tables/{id}`
- **Description** : Mettre à jour une table
- **Authentification** : Requise (Bearer token)
- **Paramètres** : Même structure que POST
- **Réponse** : Table mise à jour

### `DELETE /api/tables/{id}`
- **Description** : Supprimer une table
- **Authentification** : Requise (Bearer token)
- **Paramètres** : `id` (ID de la table)
- **Réponse** : Message de confirmation (204)
- **Note** : Impossible si la table a des ventes actives

## Routes Spécialisées

### `GET /api/tables/available`
- **Description** : Récupérer les tables disponibles
- **Authentification** : Requise (Bearer token)
- **Paramètres** : Aucun
- **Réponse** : Liste des tables avec status "available"

### `GET /api/tables/occupied`
- **Description** : Récupérer les tables occupées
- **Authentification** : Requise (Bearer token)
- **Paramètres** : Aucun
- **Réponse** : Liste des tables avec status "occupied" + dernière vente active

### `PATCH /api/tables/{id}/status`
- **Description** : Changer le statut d'une table
- **Authentification** : Requise (Bearer token)
- **Paramètres** :
  ```json
  {
    "status": "available|occupied|reserved|out_of_order"
  }
  ```
- **Réponse** : Table avec le nouveau statut

### `GET /api/tables/statistics`
- **Description** : Récupérer les statistiques des tables
- **Authentification** : Requise (Bearer token)
- **Paramètres** : Aucun
- **Réponse** :
  ```json
  {
    "total_tables": 15,
    "available_tables": 8,
    "occupied_tables": 5,
    "reserved_tables": 1,
    "out_of_order_tables": 1,
    "occupancy_rate": 33.3
  }
  ```

## Statuts des Tables

- `available` : Table disponible
- `occupied` : Table occupée (avec vente en cours)
- `reserved` : Table réservée
- `out_of_order` : Table hors service

## Sécurité

Toutes les routes nécessitent une authentification via token Bearer Sanctum et vérifient automatiquement que l'utilisateur a accès au point de vente approprié.

## Exemples d'utilisation

### Récupérer les tables disponibles
```bash
GET /api/tables/available
Authorization: Bearer YOUR_TOKEN
```

### Créer une nouvelle table
```bash
POST /api/tables
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "table_number": "T15",
  "name": "Table VIP",
  "capacity": 6,
  "description": "Table près de la fenêtre"
}
```

### Changer le statut d'une table
```bash
PATCH /api/tables/1/status
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "status": "occupied"
}
