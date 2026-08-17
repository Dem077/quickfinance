<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    /**
     * @var list<string>
     */
    private array $hiddenPropertyKeys = [
        'password',
        'password_confirmation',
        'remember_token',
        'signature',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Activity::class);

        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $logName = $request->string('log_name')->trim()->toString();
        $event = $request->string('event')->trim()->toString();
        $causerId = $request->integer('causer_id') ?: null;
        $dateFrom = $request->string('date_from')->trim()->toString() ?: null;
        $dateTo = $request->string('date_to')->trim()->toString() ?: null;

        $baseQuery = fn () => Activity::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhere('log_name', 'like', "%{$search}%")
                        ->orWhereHasMorph('causer', [User::class], fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($event !== '', fn ($query) => $query->where('event', $event))
            ->when($causerId, fn ($query) => $query->where('causer_id', $causerId))
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo));

        $logNames = Activity::query()
            ->select('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->filter()
            ->values();

        $tabs = [
            [
                'key' => 'all',
                'label' => 'All',
                'badge' => $baseQuery()->count(),
                'tone' => 'neutral',
            ],
            ...$logNames->map(fn (string $name): array => [
                'key' => $name,
                'label' => Str::headline($name),
                'badge' => $baseQuery()->where('log_name', $name)->count(),
                'tone' => match (Str::lower($name)) {
                    'access' => 'danger',
                    'resource' => 'success',
                    'model' => 'warn',
                    'notification' => 'brand',
                    default => 'neutral',
                },
            ])->all(),
        ];

        if ($logName !== 'all' && $logName !== '' && ! $logNames->contains($logName)) {
            $logName = 'all';
        }

        $activities = $baseQuery()
            ->with(['causer'])
            ->when($logName !== '' && $logName !== 'all', fn ($query) => $query->where('log_name', $logName))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Activity $activity): array => $this->activitySummary($activity, $user));

        $causerIds = Activity::query()
            ->where('causer_type', User::class)
            ->whereNotNull('causer_id')
            ->distinct()
            ->orderBy('causer_id')
            ->limit(300)
            ->pluck('causer_id');

        return Inertia::render('Activity/Index', [
            'activities' => $activities,
            'filters' => [
                'search' => $search,
                'log_name' => $logName ?: 'all',
                'event' => $event,
                'causer_id' => $causerId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'tabs' => $tabs,
            'filterOptions' => [
                'events' => Activity::query()
                    ->select('event')
                    ->whereNotNull('event')
                    ->where('event', '!=', '')
                    ->distinct()
                    ->orderBy('event')
                    ->pluck('event')
                    ->values(),
                'causers' => User::query()
                    ->whereIn('id', $causerIds)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            'can' => [
                'delete' => $user->can('delete_activity'),
                'deleteAny' => $user->can('deleteAny', Activity::class),
            ],
        ]);
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $this->authorize('delete', $activity);

        $activity->delete();

        return back()->with('success', 'Activity deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', Activity::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:activity_log,id'],
        ]);

        Activity::query()->whereIn('id', $data['ids'])->delete();

        return back()->with('success', 'Activities deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function activitySummary(Activity $activity, User $user): array
    {
        $properties = $activity->properties?->toArray() ?? [];
        $attributes = is_array($properties['attributes'] ?? null) ? $properties['attributes'] : [];
        $old = is_array($properties['old'] ?? null) ? $properties['old'] : [];
        unset($properties['attributes'], $properties['old']);

        $changes = $this->formatChanges($old, $attributes);
        $extra = $this->sanitizeProperties($properties);

        $subjectLabel = $activity->subject_type
            ? Str::of($activity->subject_type)->afterLast('\\')->headline().' #'.$activity->subject_id
            : null;

        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'log_label' => $activity->log_name ? Str::headline($activity->log_name) : 'Log',
            'event' => $activity->event,
            'event_label' => $activity->event ? Str::headline($activity->event) : ($activity->description ?: 'Activity'),
            'description' => $activity->description,
            'subject' => $subjectLabel,
            'causer' => $activity->causer?->name ?? 'System',
            'causer_email' => $activity->causer?->email,
            'created_at' => optional($activity->created_at)?->toIso8601String(),
            'created_at_label' => optional($activity->created_at)?->format('d/m/Y h:i A'),
            'day' => optional($activity->created_at)?->toDateString(),
            'changes' => $changes,
            'extra_properties' => $extra,
            'has_details' => $changes !== [] || $extra !== [],
            'can_delete' => $user->can('delete', $activity),
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $attributes
     * @return list<array{field: string, label: string, old: string|null, new: string|null}>
     */
    private function formatChanges(array $old, array $attributes): array
    {
        $keys = collect(array_keys($old))
            ->merge(array_keys($attributes))
            ->unique()
            ->reject(fn ($key) => in_array((string) $key, $this->hiddenPropertyKeys, true))
            ->values();

        return $keys->map(function ($key) use ($old, $attributes): array {
            $oldValue = array_key_exists($key, $old) ? $this->stringifyValue($old[$key]) : null;
            $newValue = array_key_exists($key, $attributes) ? $this->stringifyValue($attributes[$key]) : null;

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
    private function sanitizeProperties(array $properties): array
    {
        return collect($properties)
            ->reject(fn ($value, $key) => in_array((string) $key, $this->hiddenPropertyKeys, true))
            ->map(fn ($value, $key): array => [
                'label' => Str::of((string) $key)->replace('_', ' ')->headline()->toString(),
                'value' => $this->stringifyValue($value) ?? '—',
            ])
            ->values()
            ->all();
    }

    private function stringifyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $this->truncate((string) $encoded);
        }

        $string = is_scalar($value) ? (string) $value : json_encode($value);

        if (str_starts_with($string, 'data:image')) {
            return '[image]';
        }

        return $this->truncate($string);
    }

    private function truncate(string $value, int $limit = 180): string
    {
        return Str::limit($value, $limit);
    }
}
