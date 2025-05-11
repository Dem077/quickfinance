<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesWithPermissions = '[{"name":"super_admin","guard_name":"web","permissions":["view_activity","view_any_activity","create_activity","update_activity","delete_activity","delete_any_activity","view_advance::form","view_any_advance::form","create_advance::form","update_advance::form","delete_advance::form","delete_any_advance::form","view_budget::accounts","view_any_budget::accounts","create_budget::accounts","update_budget::accounts","delete_budget::accounts","delete_any_budget::accounts","view_budget::transaction::history","view_any_budget::transaction::history","view_budget::transfer","view_any_budget::transfer","create_budget::transfer","update_budget::transfer","delete_budget::transfer","delete_any_budget::transfer","view_departments","view_any_departments","create_departments","update_departments","delete_departments","delete_any_departments","view_item","view_any_item","create_item","update_item","delete_item","delete_any_item","view_location","view_any_location","create_location","update_location","delete_location","delete_any_location","view_petty::cash::reimbursment","view_any_petty::cash::reimbursment","create_petty::cash::reimbursment","update_petty::cash::reimbursment","delete_petty::cash::reimbursment","delete_any_petty::cash::reimbursment","view_project","view_any_project","create_project","update_project","delete_project","delete_any_project","view_purchase::orders","view_any_purchase::orders","create_purchase::orders","update_purchase::orders","delete_purchase::orders","delete_any_purchase::orders","view_purchase::requests","view_any_purchase::requests","create_purchase::requests","update_purchase::requests","delete_purchase::requests","delete_any_purchase::requests","send_approval_purchase::requests","view_role","view_any_role","create_role","update_role","delete_role","delete_any_role","view_user","view_any_user","create_user","update_user","delete_user","delete_any_user","view_vendors","view_any_vendors","create_vendors","update_vendors","delete_vendors","delete_any_vendors","page_Themes","widget_OverlookWidget","widget_LatestAccessLogs"]},{"name":"Finance","guard_name":"web","permissions":["view_budget::transaction::history","view_any_budget::transaction::history","create_budget::transaction::history","update_budget::transaction::history","delete_budget::transaction::history","delete_any_budget::transaction::history","view_budget::transfer","view_any_budget::transfer","create_budget::transfer","update_budget::transfer","delete_budget::transfer","delete_any_budget::transfer","view_purchase::orders","view_any_purchase::orders","create_purchase::orders","update_purchase::orders","delete_purchase::orders","delete_any_purchase::orders","view_purchase::requests","view_any_purchase::requests","create_purchase::requests","update_purchase::requests","delete_purchase::requests","delete_any_purchase::requests","approve_purchase::requests"]}]';
        $directPermissions = '[]';

        static::makeRolesWithPermissions($rolesWithPermissions);
        static::makeDirectPermissions($directPermissions);

        $this->command->info('Shield Seeding Completed.');
    }

    protected static function makeRolesWithPermissions(string $rolesWithPermissions): void
    {
        if (! blank($rolePlusPermissions = json_decode($rolesWithPermissions, true))) {
            /** @var Model $roleModel */
            $roleModel = Utils::getRoleModel();
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($rolePlusPermissions as $rolePlusPermission) {
                $role = $roleModel::firstOrCreate([
                    'name' => $rolePlusPermission['name'],
                    'guard_name' => $rolePlusPermission['guard_name'],
                ]);

                if (! blank($rolePlusPermission['permissions'])) {
                    $permissionModels = collect($rolePlusPermission['permissions'])
                        ->map(fn ($permission) => $permissionModel::firstOrCreate([
                            'name' => $permission,
                            'guard_name' => $rolePlusPermission['guard_name'],
                        ]))
                        ->all();

                    $role->syncPermissions($permissionModels);
                }
            }
        }
    }

    public static function makeDirectPermissions(string $directPermissions): void
    {
        if (! blank($permissions = json_decode($directPermissions, true))) {
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($permissions as $permission) {
                if ($permissionModel::whereName($permission)->doesntExist()) {
                    $permissionModel::create([
                        'name' => $permission['name'],
                        'guard_name' => $permission['guard_name'],
                    ]);
                }
            }
        }
    }
}
