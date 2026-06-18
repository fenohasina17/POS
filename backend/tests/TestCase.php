<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Le registrar Spatie met en cache les permissions/rôles en mémoire
        // (propriété $permissions sur le singleton). Sans ce reset, une collection
        // périmée d'un test précédent peut provoquer PermissionDoesNotExist ou une
        // violation FK sur role_has_permissions lorsque les IDs changent après
        // rollback RefreshDatabase + séquence Postgres non réinitialisée.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
