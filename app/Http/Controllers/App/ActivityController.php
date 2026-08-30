<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
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
            ->through(fn (Activity $activity): array => ActivityPresenter::summary($activity, $user));

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
}
