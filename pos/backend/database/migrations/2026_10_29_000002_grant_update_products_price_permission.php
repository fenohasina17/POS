<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Les caisses déjà installées ne rejouent pas pos:bootstrap-admin : la
     * nouvelle permission doit arriver par migration (jouée au démarrage du
     * conteneur), sinon le gérant n'aurait jamais le droit de modifier un prix.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name'       => 'update.products.price',
            'guard_name' => 'api',
        ]);

        foreach (['admin', 'gérant'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'api')->first()
                ?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'update.products.price')->where('guard_name', 'api')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
