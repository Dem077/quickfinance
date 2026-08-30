<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        PermissionCatalog::sync();

        $financePermissions = [
            'view_budget::transaction::history',
            'view_any_budget::transaction::history',
            'create_budget::transaction::history',
            'update_budget::transaction::history',
            'delete_budget::transaction::history',
            'delete_any_budget::transaction::history',
            'view_budget::transfer',
            'view_any_budget::transfer',
            'create_budget::transfer',
            'update_budget::transfer',
            'delete_budget::transfer',
            'delete_any_budget::transfer',
            'view_purchase::orders',
            'view_any_purchase::orders',
            'create_purchase::orders',
            'update_purchase::orders',
            'delete_purchase::orders',
            'delete_any_purchase::orders',
            'view_purchase::requests',
            'view_any_purchase::requests',
            'create_purchase::requests',
            'update_purchase::requests',
            'delete_purchase::requests',
            'delete_any_purchase::requests',
            'approve_purchase::requests',
        ];

        $roleModel = Utils::getRoleModel();
        $permissionModel = Utils::getPermissionModel();

        $finance = $roleModel::firstOrCreate([
            'name' => 'Finance',
            'guard_name' => PermissionCatalog::GUARD,
        ]);

        $finance->syncPermissions(
            collect($financePermissions)
                ->map(fn (string $permission) => $permissionModel::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => PermissionCatalog::GUARD,
                ]))
                ->all()
        );

        $this->command?->info('Shield Seeding Completed.');
    }
}
