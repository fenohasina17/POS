# Contrôle d'accès basé sur les rôles dans la vue POS

## Vue d'ensemble

Cette implémentation permet d'afficher uniquement les menus appropriés selon le rôle de l'utilisateur dans la vue POS.

## Fonctionnalités implémentées

### 1. Composable useAuth

- `useAuth()` fournit des méthodes pour vérifier les rôles et permissions
- `isAdmin` : boolean qui indique si l'utilisateur a le rôle admin
- `loadUserData()` : charge les données utilisateur au montage du composant

### 2. Vue POS modifiée

- Les sections admin ne sont affichées que si `isAdmin === true`
- Les utilisateurs simples voient uniquement les menus de base
- Les administrateurs voient toutes les options

### 3. Menus conditionnels

**Utilisateurs simples :**

- Direct
- Table
- Produits
- Ventes
- Mes ventes
- Retour

**Administrateurs :**

- Tous les menus ci-dessus PLUS
- Gestion des Rôles
- Gestion des Permissions
- Gestion des Utilisateurs

## Utilisation

Le système vérifie automatiquement les rôles de l'utilisateur connecté et adapte l'affichage en conséquence.

## Sécurité

- Les vérifications sont effectuées côté client
- Les appels API côté serveur doivent également valider les permissions
