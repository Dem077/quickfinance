<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityPresenter
{
    /**
     * @var list<string>
     */
    private static array $hiddenPropertyKeys = [
        'password',
        'password_confirmation',
        'remember_token',
        'signature',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function summary(Activity $activity, ?User $user = null, ?string $subjectLabel = null): array
    {
        $properties = $activity->properties?->toArray() ?? [];
        $attributes = is_array($properties['attributes'] ?? null) ? $properties['attributes'] : [];
        $old = is_array($properties['old'] ?? null) ? $properties['old'] : [];
        unset($properties['attributes'], $properties['old']);

        // Filament Resource logger stores changed fields as a flat map of new values.
        if ($attributes === [] && $old === [] && $properties !== []) {
            $attributes = $properties;
            $properties = [];
        }

        $changes = self::formatChanges($old, $attributes);
        $extra = self::sanitizeProperties($properties);

        $subject = $subjectLabel ?? ($activity->subject_type
            ? Str::of($activity->subject_type)->afterLast('\\')->headline().' #'.$activity->subject_id
            : null);

        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'log_label' => $activity->log_name ? Str::headline($activity->log_name) : 'Log',
            'event' => $activity->event,
            'event_label' => $activity->event ? Str::headline($activity->event) : ($activity->description ?: 'Activity'),
            'description' => $activity->description,
            'subject' => $subject,
            'causer' => $activity->causer?->name ?? 'System',
            'causer_email' => $activity->causer?->email,
            'created_at' => optional($activity->created_at)?->toIso8601String(),
            'created_at_label' => optional($activity->created_at)?->format('d/m/Y h:i A'),
            'day' => optional($activity->created_at)?->toDateString(),
            'changes' => $changes,
            'extra_properties' => $extra,
            'has_details' => $changes !== [] || $extra !== [],
            'can_delete' => $user?->can('delete', $activity) ?? false,
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $attributes
     * @return list<array{field: string, label: string, old: string|null, new: string|null}>
     */
    private static function formatChanges(array $old, array $attributes): array
    {
        $keys = collect(array_keys($old))
            ->merge(array_keys($attributes))
            ->unique()
            ->reject(fn ($key) => in_array((string) $key, self::$hiddenPropertyKeys, true))
            ->values();

        return $keys->map(function ($key) use ($old, $attributes): array {
            $oldValue = array_key_exists($key, $old) ? self::stringifyValue($old[$key]) : null;
            $newValue = array_key_exists($key, $attributes) ? self::stringifyValue($attributes[$key]) : null;

            return [
                'field' => (string) $key,
                'label' => Str::of((string) $key)->replace('_', ' ')->headline()->toString(),
                'old' => $oldValue,
                'new' => $newValue,
            ];
        })->filter(fn (array $row): bool => $row['old'] !== $row['new'])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return list<array{label: string, value: string}>
     */
    private static function sanitizeProperties(array $properties): array
    {
        return collect($properties)
            ->reject(fn ($value, $key) => in_array((string) $key, self::$hiddenPropertyKeys, true))
            ->map(fn ($value, $key): array => [
                'label' => Str::of((string) $key)->replace('_', ' ')->headline()->toString(),
                'value' => self::stringifyValue($value) ?? '—',
            ])
            ->values()
            ->all();
    }

    private static function stringifyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return self::truncate((string) $encoded);
        }

        $string = is_scalar($value) ? (string) $value : json_encode($value);

        if (str_starts_with((string) $string, 'data:image')) {
            return '[image]';
        }

        return self::truncate((string) $string);
    }

    private static function truncate(string $value, int $limit = 180): string
    {
        return Str::limit($value, $limit);
    }
}
