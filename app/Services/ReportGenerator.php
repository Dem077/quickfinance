<?php

namespace App\Services;

use App\Models\ReportTemplate;
use App\Support\ReportFieldCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportGenerator
{
    public function download(ReportTemplate $template): StreamedResponse
    {
        $modelClass = ReportFieldCatalog::modelClass($template->model_type);

        if (! $modelClass) {
            abort(422, 'Invalid report model.');
        }

        $fieldCatalog = ReportFieldCatalog::fieldsFor($template->model_type);
        $fieldConfigs = collect($template->field_configs ?? [])->filter(fn ($config) => filled($config['field'] ?? null));

        if ($fieldConfigs->isEmpty()) {
            abort(422, 'Add at least one field to export.');
        }

        $selectedFields = $fieldConfigs->pluck('field')->values();
        $query = $this->buildQuery($modelClass, $template, $fieldConfigs);
        $records = $query->get();

        $csv = Writer::createFromFileObject(new SplTempFileObject);

        $csv->insertOne(
            $selectedFields
                ->map(fn (string $field) => $fieldCatalog[$field]['label'] ?? $field)
                ->all()
        );

        foreach ($records as $record) {
            $csv->insertOne(
                $selectedFields
                    ->map(fn (string $field) => $this->resolveFieldValue($record, $field))
                    ->all()
            );
        }

        $filename = str($template->name)->slug().'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print ($csv->toString()),
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * @param  class-string  $modelClass
     */
    protected function buildQuery(string $modelClass, ReportTemplate $template, Collection $fieldConfigs): Builder
    {
        /** @var Builder $query */
        $query = $modelClass::query();

        $relations = $fieldConfigs
            ->pluck('field')
            ->filter(fn (string $field) => str_contains($field, '.'))
            ->map(fn (string $field) => explode('.', $field, 2)[0])
            ->unique()
            ->values()
            ->all();

        if ($relations !== []) {
            $query->with($relations);
        }

        foreach ($fieldConfigs as $config) {
            $field = $config['field'] ?? null;
            $filterType = $config['filter_type'] ?? null;

            if (! filled($field) || ! filled($filterType) || str_contains($field, '.')) {
                continue;
            }

            $this->applyFilter($query, $field, $filterType, $config);
        }

        if (filled($template->from_date) && filled($template->to_date)) {
            $query->whereBetween('created_at', [
                $template->from_date->startOfDay(),
                $template->to_date->endOfDay(),
            ]);
        }

        return $query;
    }

    protected function applyFilter(Builder $query, string $field, string $filterType, array $config): void
    {
        match ($filterType) {
            'equals' => $query->where($field, $config['filter_value'] ?? null),
            'contains' => $query->where($field, 'like', '%'.($config['filter_value'] ?? '').'%'),
            'starts_with' => $query->where($field, 'like', ($config['filter_value'] ?? '').'%'),
            'ends_with' => $query->where($field, 'like', '%'.($config['filter_value'] ?? '')),
            'greater_than' => $query->where($field, '>', $config['filter_value'] ?? null),
            'less_than' => $query->where($field, '<', $config['filter_value'] ?? null),
            'between' => $query->whereBetween($field, [
                $config['filter_value_from'] ?? null,
                $config['filter_value_to'] ?? null,
            ]),
            'in' => $query->whereIn($field, $config['filter_values'] ?? []),
            default => null,
        };
    }

    protected function resolveFieldValue(object $record, string $field): string
    {
        if (! str_contains($field, '.')) {
            return $this->formatValue($record->{$field} ?? null);
        }

        $value = $record;

        foreach (explode('.', $field) as $segment) {
            $value = $value?->{$segment};
        }

        return $this->formatValue($value);
    }

    protected function formatValue(mixed $value): string
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
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : $value::class;
        }

        return (string) $value;
    }
}
