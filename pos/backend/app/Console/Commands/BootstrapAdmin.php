<?php

namespace App\Console\Commands;

use App\Models\PointOfSale;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class BootstrapAdmin extends Command
{
    protected $signature = 'pos:bootstrap-admin
        {restaurant_id : Code du point de vente (ex: 101) — utilisé comme nom du point de vente local}
        {--email=admin@igp.group}
        {--password=@dminInfoiGP}';

    protected $description = "Crée les rôles, un point de vente local, et un compte admin — idempotent, sûr à rejouer.";

    public function handle(): int
    {
        $restaurantId = $this->argument('restaurant_id');
        $email        = $this->option('email');
        $password     = $this->option('password');

        // RoleSeeder crée admin/gérant/caissier (firstOrCreate, sûr à rejouer).
        // PermissionSeeder crée les permissions CRUD et donne déjà TOUTES les
        // permissions au rôle admin (givePermissionTo) — inutile de le refaire
        // ici. On n'appelle PAS RolePermissionRelationSeeder : il fait un
        // truncate() puis réassigne les rôles aux 3 premiers utilisateurs par
        // ordre de création, une logique de données de démo incompatible avec
        // un bootstrap à un seul compte admin.
        (new RoleSeeder())->run();
        (new PermissionSeeder())->run();

        // Point de vente local, nommé d'après le code réel du restaurant
        $pos = PointOfSale::firstOrCreate(['name' => $restaurantId]);

        // Compte admin — mot de passe en clair : le cast 'hashed' du modèle
        // User s'occupe du hachage. Le repasser dans bcrypt() ici le
        // hacherait deux fois et casserait la connexion.
        $admin = User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Administrateur', 'password' => $password]
        );

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        if ($adminRole && ! $admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }

        $admin->point_of_sale_id = $pos->id;
        $admin->save();

        if (! $admin->pointsOfSale()->where('point_of_sale_id', $pos->id)->exists()) {
            $admin->pointsOfSale()->attach($pos->id);
        }

        $this->info("Point de vente '{$pos->name}' prêt. Admin : {$email} / {$password}");
        $this->warn('Pense à changer ce mot de passe une fois connecté.');

        return self::SUCCESS;
    }
}
