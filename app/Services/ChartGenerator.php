<?php

namespace App\Services;

use App\Models\ChartTemplate;
use App\Support\ReportFieldCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ChartGenerator
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     description: ?string,
     *     chart_type: string,
     *     aggregation: string,
     *     model_label: string,
     *     group_label: string,
     *     metric_label: ?string,
     *     labels: list<string>,
     *     values: list<float>,
     *     total: float
     * }
     */
    public function series(ChartTemplate $template): array
    {
        $modelClass = ReportFieldCatalog::modelClass($template->model_type);
        $catalog = ReportFieldCatalog::fieldsFor($template->model_type);
        $groupBy = $template->group_by;
        $filters = collect($template->filters ?? [])->filter(fn ($config) => filled($config['field'] ?? null));

        if (! $modelClass || ! filled($groupBy)) {
            return $this->emptySeries($template, $catalog);
        }

        $query = $modelClass::query();
        $this->eagerLoad($query, $filters->pluck('field')->push($groupBy)->push($template->metric_field)->filter()->all());

        foreach ($filters as $config) {
            $field = $config['field'] ?? null;
            $filterType = $config['filter_type'] ?? null;

            if (! filled($field) || ! filled($filterType) || ! $this->filterHasValue($filterType, $config)) {
                continue;
            }

            $this->applyConfiguredFilter($query, $field, $filterType, $config);
        }

        if (filled($template->from_date) && filled($template->to_date)) {
            $query->whereBetween('created_at', [
                $template->from_date->copy()->startOfDay(),
                $template->to_date->copy()->endOfDay(),
            ]);
        } elseif (filled($template->from_date)) {
            $query->where('created_at', '>=', $template->from_date->copy()->startOfDay());
        } elseif (filled($template->to_date)) {
            $query->where('created_at', '<=', $template->to_date->copy()->endOfDay());
        }

        $records = $query->limit(5000)->get();
        $buckets = [];

        foreach ($records as $record) {
            $label = $this->groupLabel($record, $groupBy, $template->group_period ?: 'value', $catalog);
            $buckets[$label] ??= 0;

            if ($template->aggregation === 'count') {
                $buckets[$label]++;

                continue;
            }

            $metric = $this->numericValue($record, $template->metric_field);
            $buckets[$label] += $metric;
        }

        if ($template->aggregation === 'avg') {
            $counts = [];
            foreach ($records as $record) {
                $label = $this->groupLabel($record, $groupBy, $template->group_period ?: 'value', $catalog);
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
            foreach ($buckets as $label => $sum) {
                $buckets[$label] = ($counts[$label] ?? 0) > 0 ? round($sum / $counts[$label], 2) : 0;
            }
        }

        arsort($buckets);
        $limit = in_array($template->chart_type, ['pie', 'doughnut'], true) ? 12 : 20;
        $buckets = array_slice($buckets, 0, $limit, true);

        $labels = array_keys($buckets);
        $values = array_map(fn ($value) => round((float) $value, 2), array_values($buckets));

        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'chart_type' => $template->chart_type,
            'aggregation' => $template->aggregation,
            'model_label' => ReportFieldCatalog::modelLabel($template->model_type),
            'group_label' => $catalog[$groupBy]['label'] ?? $groupBy,
            'metric_label' => $template->metric_field
                ? ($catalog[$template->metric_field]['label'] ?? $template->metric_field)
                : null,
            'labels' => $labels,
            'values' => $values,
            'total' => array_sum($values),
        ];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    private function emptySeries(ChartTemplate $template, array $catalog): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'chart_type' => $template->chart_type,
            'aggregation' => $template->aggregation,
            'model_label' => ReportFieldCatalog::modelLabel($template->model_type),
            'group_label' => $catalog[$template->group_by]['label'] ?? $template->group_by,
            'metric_label' => $template->metric_field
                ? ($catalog[$template->metric_field]['label'] ?? $template->metric_field)
                : null,
            'labels' => [],
            'values' => [],
            'total' => 0,
        ];
    }

    /**
     * @param  list<string>  $fields
     */
    private function eagerLoad(Builder $query, array $fields): void
    {
        $relations = collect($fields)
            ->filter(fn (string $field) => str_contains($field, '.'))
            ->map(fn (string $field) => explode('.', $field, 2)[0])
            ->unique()
            ->values()
            ->all();

        if ($relations !== []) {
            $query->with($relations);
        }
    }

    private function filterHasValue(string $filterType, array $config): bool
    {
        return match ($filterType) {
            'between' => filled($config['filter_value_from'] ?? null) && filled($config['filter_value_to'] ?? null),
            'in' => collect($config['filter_values'] ?? [])->filter(fn ($value) => filled($value))->isNotEmpty(),
            default => filled($config['filter_value'] ?? null),
        };
    }

    private function applyConfiguredFilter(Builder $query, string $field, string $filterType, array $config): void
    {
        if (! str_contains($field, '.')) {
            $this->applyFilter($query, $field, $filterType, $config);

            return;
        }

        [$relation, $column] = explode('.', $field, 2);

        $query->whereHas($relation, function (Builder $relationQuery) use ($column, $filterType, $config): void {
            $this->applyFilter($relationQuery, $column, $filterType, $config);
        });
    }

    private function applyFilter(Builder $query, string $field, string $filterType, array $config): void
    {
        $values = collect($config['filter_values'] ?? [])
            ->filter(fn ($value) => filled($value))
            ->values()
            ->all();

        match ($filterType) {
            'equals' => $query->where($field, $config['filter_value']),
            'contains' => $query->where($field, 'like', '%'.$config['filter_value'].'%'),
            'starts_with' => $query->where($field, 'like', $config['filter_value'].'%'),
            'ends_with' => $query->where($field, 'like', '%'.$config['filter_value']),
            'greater_than' => $query->where($field, '>', $config['filter_value']),
            'less_than' => $query->where($field, '<', $config['filter_value']),
            'between' => $query->whereBetween($field, [
                $config['filter_value_from'],
                $config['filter_value_to'],
            ]),
            'in' => $query->whereIn($field, $values),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $catalog
     */
    private function groupLabel(object $record, string $field, string $period, array $catalog): string
    {
        $raw = $this->rawValue($record, $field);

        if ($raw === null || $raw === '') {
            return '—';
        }

        $date = $this->asDate($raw);
        if ($date && in_array($period, ['month', 'year'], true)) {
            return $period === 'year' ? $date->format('Y') : $date->format('M Y');
        }

        $options = $catalog[$field]['options'] ?? [];
        $key = $raw instanceof \BackedEnum ? $raw->value : (string) $this->formatValue($raw);

        return $options[$key] ?? $this->formatValue($raw);
    }

    private function numericValue(object $record, ?string $field): float
    {
        if (! filled($field)) {
            return 0;
        }

        $value = $this->rawValue($record, $field);

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    private function rawValue(object $record, string $field): mixed
    {
        $value = $record;

        foreach (explode('.', $field) as $segment) {
            $value = $value?->{$segment};
        }

        return $value;
    }

    private function asDate(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value);
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value)->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : '—';
        }

        return (string) $value;
    }
}
