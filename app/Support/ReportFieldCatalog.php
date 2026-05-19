<?php

namespace App\Support;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use UnitEnum;

class ReportFieldCatalog
{
    /** @var array<string, string> */
    public const MODELS = [
        'PurchaseRequests' => 'Purchase Requests',
        'PurchaseOrders' => 'Purchase Orders',
        'PettyCashReimbursment' => 'Petty Cash Reimbursements',
        'SubBudgetAccounts' => 'Sub Budget Accounts',
        'BudgetTransactionHistory' => 'Budget Transactions',
        'AdvanceForm' => 'Advance Forms',
    ];

    /** @var list<string> */
    protected const EXCLUDED_COLUMNS = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public static function modelLabel(string $modelType): string
    {
        return self::MODELS[$modelType] ?? $modelType;
    }

    /**
     * @return array<string, array{label: string, type: string, relation: ?string, input: string, options: array<string, string>}>
     */
    public static function fieldsFor(string $modelType): array
    {
        $modelClass = self::modelClass($modelType);

        if (! $modelClass) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass;

        $fields = collect(Schema::getColumnListing($model->getTable()))
            ->reject(fn (string $column) => in_array($column, self::EXCLUDED_COLUMNS, true))
            ->mapWithKeys(fn (string $column) => [
                $column => self::columnConfig($modelClass, $column, 'column', null),
            ]);

        foreach (self::relationshipMethods($model) as $relation) {
            $relationModel = $model->{$relation}()->getRelated();
            $relationTable = $relationModel->getTable();

            foreach (Schema::getColumnListing($relationTable) as $column) {
                if (in_array($column, self::EXCLUDED_COLUMNS, true)) {
                    continue;
                }

                $key = "{$relation}.{$column}";

                $fields->put($key, self::columnConfig(
                    $relationModel::class,
                    $column,
                    'relation',
                    $relation,
                    self::humanize($relation).' · '.self::humanize($column),
                ));
            }
        }

        return $fields->sortBy('label')->all();
    }

    public static function fieldConfig(string $modelType, ?string $field): ?array
    {
        if (! filled($field)) {
            return null;
        }

        return self::fieldsFor($modelType)[$field] ?? null;
    }

    public static function isStatusField(string $modelType, ?string $field): bool
    {
        $config = self::fieldConfig($modelType, $field);

        return ($config['input'] ?? 'text') === 'status';
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(string $modelType, ?string $field): array
    {
        return self::fieldConfig($modelType, $field)['options'] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public static function filterTypesForField(string $modelType, ?string $field): array
    {
        if (self::isStatusField($modelType, $field)) {
            return [
                'equals' => 'Equals',
                'in' => 'In list',
            ];
        }

        return [
            'equals' => 'Equals',
            'contains' => 'Contains',
            'starts_with' => 'Starts with',
            'ends_with' => 'Ends with',
            'greater_than' => 'Greater than',
            'less_than' => 'Less than',
            'between' => 'Between',
            'in' => 'In list',
        ];
    }

    /**
     * @return array{label: string, type: string, relation: ?string, input: string, options: array<string, string>}
     */
    protected static function columnConfig(
        string $modelClass,
        string $column,
        string $type,
        ?string $relation,
        ?string $label = null,
    ): array {
        $casts = (new $modelClass)->getCasts();
        $options = self::enumOptionsForColumn($casts, $column);

        return [
            'label' => $label ?? self::humanize($column),
            'type' => $type,
            'relation' => $relation,
            'input' => $options !== [] ? 'status' : 'text',
            'options' => $options,
        ];
    }

    /**
     * @param  array<string, string>  $casts
     * @return array<string, string>
     */
    protected static function enumOptionsForColumn(array $casts, string $column): array
    {
        if (! self::isStatusColumn($column) || ! isset($casts[$column])) {
            return [];
        }

        return self::enumOptions($casts[$column]);
    }

    protected static function isStatusColumn(string $column): bool
    {
        return $column === 'status' || str_ends_with($column, '_status');
    }

    /**
     * @return array<string, string>
     */
    protected static function enumOptions(string $enumClass): array
    {
        if (! enum_exists($enumClass)) {
            return [];
        }

        return collect($enumClass::cases())
            ->mapWithKeys(function (UnitEnum $case) {
                $value = $case instanceof \BackedEnum ? $case->value : $case->name;
                $label = $case instanceof HasLabel
                    ? ($case->getLabel() ?? self::humanize($value))
                    : self::humanize($value);

                return [$value => $label];
            })
            ->all();
    }

    public static function modelClass(string $modelType): ?string
    {
        $class = "App\\Models\\{$modelType}";

        return class_exists($class) ? $class : null;
    }

    /**
     * @return list<string>
     */
    protected static function relationshipMethods(Model $model): array
    {
        $reflection = new ReflectionClass($model);

        return collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method) => $method->getDeclaringClass()->getName() === $model::class)
            ->filter(fn (ReflectionMethod $method) => $method->getNumberOfRequiredParameters() === 0)
            ->filter(function (ReflectionMethod $method) use ($model) {
                try {
                    $result = $method->invoke($model);

                    return $result instanceof Relation;
                } catch (\Throwable) {
                    return false;
                }
            })
            ->map(fn (ReflectionMethod $method) => $method->getName())
            ->values()
            ->all();
    }

    protected static function humanize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
