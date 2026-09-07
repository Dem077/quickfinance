<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Services\ReportGenerator;
use App\Support\ReportFieldCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ReportTemplate::class);

        $search = $request->string('search')->trim()->toString();
        $modelType = $request->string('model_type')->trim()->toString();

        $reports = ReportTemplate::query()
            ->with('creator')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($modelType !== '', fn ($q) => $q->where('model_type', $modelType))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ReportTemplate $report): array => [
                'id' => $report->id,
                'name' => $report->name,
                'description' => $report->description,
                'model_type' => $report->model_type,
                'model_label' => ReportFieldCatalog::modelLabel($report->model_type),
                'column_count' => count($report->field_configs ?? []),
                'creator' => $report->creator?->name,
                'created_at' => optional($report->created_at)?->format('d M Y H:i'),
            ]);

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'filters' => ['search' => $search, 'model_type' => $modelType],
            'modelTypes' => collect(ReportFieldCatalog::MODELS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'can' => [
                'create' => $request->user()->can('create', ReportTemplate::class),
                'deleteAny' => $request->user()->can('deleteAny', ReportTemplate::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ReportTemplate::class);

        return Inertia::render('Reports/Form', [
            'report' => null,
                        'catalog' => ReportFieldCatalog::inertiaCatalog(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ReportTemplate::class);

        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        $report = ReportTemplate::create($data);

        return redirect()
            ->route('app.reports.index')
            ->with('success', 'Report template created.');
    }

    public function edit(ReportTemplate $report): Response
    {
        $this->authorize('update', $report);

        return Inertia::render('Reports/Form', [
            'report' => [
                'id' => $report->id,
                'name' => $report->name,
                'description' => $report->description,
                'model_type' => $report->model_type,
                'field_configs' => $report->field_configs ?? [],
                'from_date' => optional($report->from_date)?->format('Y-m-d'),
                'to_date' => optional($report->to_date)?->format('Y-m-d'),
            ],
                        'catalog' => ReportFieldCatalog::inertiaCatalog(),
        ]);
    }

    public function update(Request $request, ReportTemplate $report): RedirectResponse
    {
        $this->authorize('update', $report);

        $report->update($this->validated($request));

        return redirect()
            ->route('app.reports.index')
            ->with('success', 'Report template updated.');
    }

    public function destroy(ReportTemplate $report): RedirectResponse
    {
        $this->authorize('delete', $report);

        $report->delete();

        return redirect()
            ->route('app.reports.index')
            ->with('success', 'Report template deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', ReportTemplate::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:report_templates,id'],
        ]);

        ReportTemplate::query()->whereIn('id', $data['ids'])->delete();

        return back()->with('success', 'Report templates deleted.');
    }

    public function download(ReportTemplate $report, ReportGenerator $generator): StreamedResponse
    {
        $this->authorize('view', $report);

        return $generator->download($report);
    }

    public function fields(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', ReportTemplate::class);

        $modelType = $request->string('model_type')->toString();

        return response()->json([
            'fields' => ReportFieldCatalog::inertiaFields($modelType),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'model_type' => ['required', 'string', 'in:'.implode(',', array_keys(ReportFieldCatalog::MODELS))],
            'field_configs' => ['required', 'array', 'min:1'],
            'field_configs.*.heading' => ['nullable', 'string', 'max:255'],
            'field_configs.*.filter_type' => ['nullable', 'string'],
            'field_configs.*.filter_value' => ['nullable'],
            'field_configs.*.filter_value_from' => ['nullable'],
            'field_configs.*.filter_value_to' => ['nullable'],
            'field_configs.*.filter_values' => ['nullable', 'array'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);
    }
}
