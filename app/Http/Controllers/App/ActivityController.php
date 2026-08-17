<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
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

        $search = $request->string('search')->trim()->toString();
        $logName = $request->string('log_name')->trim()->toString();

        $activities = Activity::query()
            ->with('causer')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhereHasMorph('causer', ['App\\Models\\User'], fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($logName !== '', fn ($q) => $q->where('log_name', $logName))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Activity $activity): array => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'event' => $activity->event,
                'description' => $activity->description,
                'subject' => $activity->subject_type
                    ? Str::of($activity->subject_type)->afterLast('\\')->headline().' #'.$activity->subject_id
                    : null,
                'causer' => $activity->causer?->name ?? '—',
                'created_at' => optional($activity->created_at)?->format('d/m/Y h:i A'),
            ]);

        $logNames = Activity::query()
            ->select('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->filter()
            ->values();

        return Inertia::render('Activity/Index', [
            'activities' => $activities,
            'filters' => ['search' => $search, 'log_name' => $logName],
            'logNames' => $logNames,
            'can' => [
                'deleteAny' => $request->user()->can('deleteAny', Activity::class),
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
