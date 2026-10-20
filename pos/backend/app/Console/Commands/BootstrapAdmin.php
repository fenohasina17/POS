<?php

namespace App\Console\Commands;

use App\Models\CashRegister;
use App\Models\PointOfSale;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Support\RolePermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BootstrapAdmin extends Command
{
    protected $signature = 'pos:bootstrap-admin
        {restaurant_id : Code du point de vente (ex: 101) — utilisé comme nom du point de vente local}
        {--email=admin@igp.group}
        {--password= : Mot de passe du compte admin à sa création (aléatoire si absent)}
        {--reset-password : Remplace aussi le mot de passe d\'un compte admin existant}';

    protected $description = "Crée les rôles, un point de vente local, et un compte admin — idempotent, sûr à rejouer.";

    public function handle(): int
    {
        $restaurantId = $this->argument('restaurant_id');
        $email        = $this->option('email');
        // Jamais de mot de passe par défaut : le dépôt est public, une valeur
        // fixe ouvrirait le compte admin de toutes les caisses à la fois.
        $password     = $this->option('password') ?: Str::password(16, symbols: false);

        // RoleSeeder crée admin/gérant/caissier (firstOrCreate, sûr à rejouer).
        // PermissionSeeder crée les permissions CRUD et donne déjà TOUTES les
        // permissions au rôle admin (givePermissionTo) — inutile de le refaire
        // ici. On n'appelle PAS RolePermissionRelationSeeder : il fait un
        // truncate() puis réassigne les rôles aux 3 premiers utilisateurs par
        // ordre de création, une logique de données de démo incompatible avec
        // un bootstrap à un seul compte admin.
        (new RoleSeeder())->run();
        (new PermissionSeeder())->run();

        // Permissions des rôles caissier et gérant, accordées ici (pas via
        // RolePermissionRelationSeeder, qui ne tourne jamais en production)
        // pour que tout compte créé ensuite localement en hérite. Sans elles,
        // un caissier ne peut ni ouvrir sa session ni vendre (403).
        // Ajout seulement : une permission accordée à la main est conservée.
        foreach (['caissier', 'gérant'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'api')->first();
            if (! $role) {
                continue;
            }
            $permissions = Permission::where('guard_name', 'api')
                ->whereIn('name', RolePermissions::forRole($roleName))
                ->get();
            $role->givePermissionTo($permissions);
        }

        // Point de vente local, nommé d'après le code réel du restaurant
        $pos = PointOfSale::firstOrCreate(['name' => $restaurantId]);

        // Une caisse enregistreuse par défaut — sans elle, aucun caissier ne
        // peut ouvrir de session tant qu'un admin n'en crée pas une à la main
        // depuis l'interface. Un point de vente qui a réellement besoin de
        // plusieurs caisses peut toujours en ajouter d'autres ensuite.
        CashRegister::firstOrCreate([
            'point_of_sale_id' => $pos->id,
            'name'             => 'Caisse principale',
        ]);

        // Compte admin — mot de passe en clair : le cast 'hashed' du modèle
        // User s'occupe du hachage. Le repasser dans bcrypt() ici le
        // hacherait deux fois et casserait la connexion.
        // Un compte existant garde son mot de passe : relancer install.sh ne
        // doit pas écraser celui que l'admin a choisi (--reset-password pour
        // forcer, ex. mot de passe perdu).
        $admin = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Administrateur', 'password' => $password]
        );
        $passwordSet = $admin->wasRecentlyCreated;

        if (! $passwordSet && $this->option('reset-password')) {
            $admin->password = $password;
            $passwordSet     = true;
        }

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        if ($adminRole && ! $admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }

        $admin->point_of_sale_id = $pos->id;
        $admin->save();

        if (! $admin->pointsOfSale()->where('point_of_sale_id', $pos->id)->exists()) {
            $admin->pointsOfSale()->attach($pos->id);
        }

        $this->info("Point de vente '{$pos->name}' prêt.");

        if ($passwordSet) {
            $this->info("Admin : {$email} / {$password}");
            $this->warn('Note ce mot de passe maintenant : il ne sera plus affiché.');
        } else {
            $this->info("Admin : {$email} — compte existant, mot de passe inchangé.");
        }

        return self::SUCCESS;
    }
}
