<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalog
{
    public const GUARD = 'web';

    /**
     * @var list<string>
     */
    public const CRUD_PREFIXES = [
        'view',
        'view_any',
        'create',
        'update',
        'delete',
        'delete_any',
    ];

    /**
     * @var array<string, string>
     */
    public const PREFIX_LABELS = [
        'view_any' => 'View any',
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'delete_any' => 'Delete any',
        'send_approval' => 'Send for approval',
        'approve' => 'Finance approve',
        'md_dmd_approve' => 'MD / DMD approve',
        'cancel' => 'Cancel',
        'close' => 'Close',
        'generate_advance_form' => 'Generate advance form',
        'md_dmd_approve_advance_form' => 'MD / DMD approve advance form',
        'pv_approve' => 'Approve and add PV',
        'fin_hod_approve' => 'Finance HOD approval',
    ];

    /**
     * Shield resource entities and the prefixes each one should expose.
     *
     * @var array<string, array{label: string, prefixes: list<string>}>
     */
    public const ENTITIES = [
        'purchase::requests' => [
            'label' => 'Purchase requests',
            'prefixes' => [
                ...self::CRUD_PREFIXES,
                'send_approval',
                'approve',
                'md_dmd_approve',
                'cancel',
                'close',
            ],
        ],
        'purchase::orders' => [
            'label' => 'Purchase orders',
            'prefixes' => [
                ...self::CRUD_PREFIXES,
                'generate_advance_form',
                'md_dmd_approve_advance_form',
                'close',
            ],
        ],
        'petty::cash::reimbursment' => [
            'label' => 'Petty cash',
            'prefixes' => [
                ...self::CRUD_PREFIXES,
                'pv_approve',
                'fin_hod_approve',
            ],
        ],
        'asset::management' => [
            'label' => 'Asset management',
            'prefixes' => ['view', 'view_any'],
        ],
        'advance::form' => [
            'label' => 'Advance forms',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'budget::accounts' => [
            'label' => 'Budget accounts',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'budget::transfer' => [
            'label' => 'Budget transfers',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'budget::transaction::history' => [
            'label' => 'Budget history',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'report' => [
            'label' => 'Report templates',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'activity' => [
            'label' => 'Activity log',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'item' => [
            'label' => 'Items',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'vendors' => [
            'label' => 'Vendors',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'location' => [
            'label' => 'Locations',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'project' => [
            'label' => 'Projects',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'departments' => [
            'label' => 'Departments',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'user' => [
            'label' => 'Users',
            'prefixes' => self::CRUD_PREFIXES,
        ],
        'role' => [
            'label' => 'Roles',
            'prefixes' => self::CRUD_PREFIXES,
        ],
    ];

    /**
     * Filament page / widget permissions that are not resource CRUD.
     *
     * @var array<string, array{group: string, label: string}>
     */
    public const EXTRAS = [
        'page_Themes' => ['group' => 'pages', 'label' => 'Themes'],
        'widget_OverlookWidget' => ['group' => 'widgets', 'label' => 'Overlook'],
        'widget_LatestAccessLogs' => ['group' => 'widgets', 'label' => 'Latest access logs'],
        'widget_FinanceStatsOverview' => ['group' => 'widgets', 'label' => 'Finance stats'],
        'widget_PendingAdvanceFormsTable' => ['group' => 'widgets', 'label' => 'Pending advance forms'],
        'widget_PendingPurchaseRequestsTable' => ['group' => 'widgets', 'label' => 'Pending purchase requests'],
        'widget_PurchaseRequestStatusChart' => ['group' => 'widgets', 'label' => 'Purchase request status chart'],
    ];

    /**
     * @var array<string, string>
     */
    public const GROUP_LABELS = [
        'pages' => 'Pages',
        'widgets' => 'Widgets',
        'other' => 'Other',
    ];

    /**
     * Create any missing catalog permissions and grant them all to super_admin.
     *
     * @return array{created: int, granted: int}
     */
    public static function sync(): array
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $created = 0;
        $names = [];

        foreach (self::expectedNames() as $name) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => self::GUARD,
            ]);

            if ($permission->wasRecentlyCreated) {
                $created++;
            }

            $names[] = $name;
        }

        $granted = 0;
        $superAdmin = Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => self::GUARD,
        ]);

        $existing = $superAdmin->permissions()->pluck('name')->all();
        $missing = array_values(array_diff($names, $existing));

        if ($missing !== []) {
            $superAdmin->givePermissionTo($missing);
            $granted = count($missing);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return ['created' => $created, 'granted' => $granted];
    }

    /**
     * @return list<string>
     */
    public static function expectedNames(): array
    {
        $names = [];

        foreach (self::ENTITIES as $entity => $config) {
            foreach ($config['prefixes'] as $prefix) {
                $names[] = "{$prefix}_{$entity}";
            }
        }

        return array_values(array_unique([...$names, ...array_keys(self::EXTRAS)]));
    }

    /**
     * @return array<int, array{key: string, label: string, permissions: array<int, array{name: string, label: string}>}>
     */
    public static function groups(): array
    {
        self::sync();

        $grouped = [];

        foreach (self::ENTITIES as $entity => $config) {
            $grouped[$entity] = [
                'key' => $entity,
                'label' => $config['label'],
                'permissions' => [],
            ];
        }

        $grouped['pages'] = [
            'key' => 'pages',
            'label' => self::GROUP_LABELS['pages'],
            'permissions' => [],
        ];
        $grouped['widgets'] = [
            'key' => 'widgets',
            'label' => self::GROUP_LABELS['widgets'],
            'permissions' => [],
        ];
        $grouped['other'] = [
            'key' => 'other',
            'label' => self::GROUP_LABELS['other'],
            'permissions' => [],
        ];

        $permissions = Permission::query()
            ->where('guard_name', self::GUARD)
            ->orderBy('name')
            ->get();

        foreach ($permissions as $permission) {
            [$groupKey, $label] = self::describe($permission->name);
            $grouped[$groupKey] ??= [
                'key' => $groupKey,
                'label' => self::entityLabel($groupKey),
                'permissions' => [],
            ];
            $grouped[$groupKey]['permissions'][] = [
                'name' => $permission->name,
                'label' => $label,
            ];
        }

        foreach ($grouped as $key => $group) {
            $grouped[$key]['permissions'] = self::sortPermissions($group['permissions'], $key);
        }

        return array_values(array_filter(
            $grouped,
            fn (array $group): bool => $group['permissions'] !== [],
        ));
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function describe(string $name): array
    {
        if (isset(self::EXTRAS[$name])) {
            return [self::EXTRAS[$name]['group'], self::EXTRAS[$name]['label']];
        }

        if (str_starts_with($name, 'page_')) {
            return ['pages', str($name)->after('page_')->headline()->toString()];
        }

        if (str_starts_with($name, 'widget_')) {
            return ['widgets', str($name)->after('widget_')->headline()->toString()];
        }

        foreach (self::prefixesByLength() as $prefix) {
            $needle = $prefix.'_';

            if (str_starts_with($name, $needle)) {
                $entity = substr($name, strlen($needle));

                return [$entity, self::PREFIX_LABELS[$prefix]];
            }
        }

        return ['other', str($name)->replace('::', ' · ')->replace('_', ' ')->headline()->toString()];
    }

    public static function entityLabel(string $entity): string
    {
        return self::ENTITIES[$entity]['label']
            ?? self::GROUP_LABELS[$entity]
            ?? str($entity)->replace('::', ' / ')->replace('_', ' ')->headline()->toString();
    }

    /**
     * @param  array<int, array{name: string, label: string}>  $permissions
     * @return array<int, array{name: string, label: string}>
     */
    private static function sortPermissions(array $permissions, string $groupKey): array
    {
        $order = array_flip(array_keys(self::PREFIX_LABELS));

        usort($permissions, function (array $a, array $b) use ($groupKey, $order): int {
            $prefixA = self::prefixOf($a['name'], $groupKey);
            $prefixB = self::prefixOf($b['name'], $groupKey);

            $rankA = $order[$prefixA] ?? 999;
            $rankB = $order[$prefixB] ?? 999;

            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return $a['label'] <=> $b['label'];
        });

        return array_values($permissions);
    }

    private static function prefixOf(string $name, string $entity): ?string
    {
        $suffix = '_'.$entity;

        if ($entity !== 'other' && str_ends_with($name, $suffix)) {
            return substr($name, 0, -strlen($suffix));
        }

        foreach (self::prefixesByLength() as $prefix) {
            if (str_starts_with($name, $prefix.'_')) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function prefixesByLength(): array
    {
        $prefixes = array_keys(self::PREFIX_LABELS);
        usort($prefixes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $prefixes;
    }
}
