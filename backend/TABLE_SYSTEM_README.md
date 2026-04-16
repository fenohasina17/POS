# Système de Gestion des Tables

Ce document décrit l'implémentation du système de gestion des tables pour le point de vente.

## Fonctionnalités Implémentées

### 1. Modèle Table
- **Champs** : id, table_number, name, capacity, status, description, location, point_of_sale_id
- **Statuts** : available, occupied, reserved, out_of_order
- **Relations** : Appartient à un point de vente, a plusieurs ventes

### 2. Contrôleur TableController
- **CRUD complet** : Création, lecture, mise à jour, suppression des tables
- **Méthodes spécialisées** :
  - `getAvailableTables()` : Récupérer les tables disponibles
  - `getOccupiedTables()` : Récupérer les tables occupées
  - `updateStatus()` : Changer le statut d'une table
  - `getStatistics()` : Statistiques d'occupation

### 3. Modèle Sale
- **Relation ajoutée** : Une vente peut être liée à une table
- **Champ ajouté** : table_id dans le fillable

### 4. Routes API
```
GET    /api/tables              # Liste toutes les tables
POST   /api/tables              # Créer une table
GET    /api/tables/{id}         # Afficher une table
PUT    /api/tables/{id}         # Mettre à jour une table
DELETE /api/tables/{id}         # Supprimer une table

GET    /api/tables/available    # Tables disponibles
GET    /api/tables/occupied     # Tables occupées
PATCH  /api/tables/{id}/status  # Changer le statut
GET    /api/tables/statistics   # Statistiques
```

## Utilisation

### Créer une table
```bash
curl -X POST http://localhost:8000/api/tables \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "table_number": "T01",
    "name": "Table 1",
    "capacity": 4,
    "description": "Table près de la fenêtre",
    "location": {"x": 10, "y": 20}
  }'
```

### Assigner une vente à une table
```bash
curl -X POST http://localhost:8000/api/sales \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "point_of_sale_id": 1,
    "cash_register_session_id": 1,
    "total_amount": 25.50,
    "payment_id": 1,
    "table_id": 1
  }'
```

### Changer le statut d'une table
```bash
curl -X PATCH http://localhost:8000/api/tables/1/status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "occupied"}'
```

## Statuts des Tables

- **available** : Table libre et prête à recevoir des clients
- **occupied** : Table occupée par des clients
- **reserved** : Table réservée à l'avance
- **out_of_order** : Table hors service (maintenance, réparation)

## Statistiques

L'endpoint `/api/tables/statistics` retourne :
- Nombre total de tables
- Nombre de tables disponibles
- Nombre de tables occupées
- Nombre de tables réservées
- Nombre de tables hors service
- Taux d'occupation (pourcentage)

## Sécurité

- Toutes les routes sont protégées par l'authentification Sanctum
- Les tables sont filtrées par point de vente de l'utilisateur
- Validation des données d'entrée
- Vérification des permissions pour les opérations sensibles

## Prochaines Étapes

1. **Interface Frontend** : Créer les composants Vue.js pour gérer les tables
2. **Plan de Salle** : Interface visuelle pour voir l'agencement des tables
3. **Réservations** : Système de réservation avancé
4. **Notifications** : Alertes pour les tables prêtes à libérer
5. **Rapports** : Statistiques détaillées sur l'utilisation des tables
