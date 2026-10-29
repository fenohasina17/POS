<?php

namespace App\Support;

/**
 * Permissions de chaque rôle — source unique pour l'installation
 * (pos:bootstrap-admin) et pour les données de démo
 * (RolePermissionRelationSeeder). L'admin a toujours toutes les
 * permissions (PermissionSeeder).
 */
class RolePermissions
{
    public const CAISSIER = [
        'view.sales', 'create.sales',
        'view.cash_register_sessions', 'create.cash_register_sessions', 'update.cash_register_sessions',
        'view.order_lines', 'create.order_lines',
        'view.payments', 'create.payments',
        'view.sale_payments', 'create.sale_payments',
        'view.cash_registers',
        'view.tables', 'update.tables',
        'view.products', 'view.categories',
    ];

    // Le catalogue reste piloté par le Central : le gérant ne modifie que le
    // statut de disponibilité, et le prix des catégories que le Central a
    // ouvertes à la modification (contrôlé dans ProductController).
    public const GERANT = [
        'view.sales', 'create.sales', 'update.sales',
        'view.cash_register_sessions', 'create.cash_register_sessions', 'update.cash_register_sessions',
        'view.order_lines', 'create.order_lines', 'update.order_lines',
        'view.payments', 'create.payments',
        'view.sale_payments',
        'view.cash_registers',
        'view.cash_transactions',
        'view.products', 'view.categories', 'update.products.status', 'update.products.price',
        'view.tables', 'update.tables',
        'view.point_of_sales',
    ];

    public static function forRole(string $role): array
    {
        return match ($role) {
            'caissier' => self::CAISSIER,
            'gérant'   => self::GERANT,
            default    => [],
        };
    }
}
