<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ChartTemplate;
use App\Services\ChartGenerator;
use App\Support\ReportFieldCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChartController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ChartTemplate::class);

        $search = $request->string('search')->trim()->toString();
        $modelType = $request->string('model_type')->trim()->toString();

        $charts = ChartTemplate::query()
            ->with('creator')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($modelType !== '', fn ($query) => $query->where('model_type', $modelType))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ChartTemplate $chart): array => $this->summary($chart));

        return Inertia::render('Charts/Index', [
            'charts' => $charts,
            'filters' => ['search' => $search, 'model_type' => $modelType],
            'modelTypes' => collect(ReportFieldCatalog::MODELS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'can' => [
                'create' => $request->user()->can('create', ChartTemplate::class),
                'deleteAny' => $request->user()->can('deleteAny', ChartTemplate::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ChartTemplate::class);

        return Inertia::render('Charts/Form', [
            'chart' => null,
            'catalog' => ReportFieldCatalog::inertiaCatalog(),
            'chartTypes' => $this->chartTypes(),
            'aggregations' => $this->aggregations(),
            'groupPeriods' => $this->groupPeriods(),
        ]);
    }

    public function preview(Request $request, ChartGenerator $charts): JsonResponse
    {
        $this->authorize('viewAny', ChartTemplate::class);

        $request->merge([
            'name' => $request->input('name') ?: 'Preview',
        ]);

        $template = new ChartTemplate($this->validated($request));

        return response()->json($charts->series($template));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ChartTemplate::class);

        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        ChartTemplate::create($data);

        return redirect()
            ->route('app.charts.index')
            ->with('success', 'Chart created.');
    }

    public function edit(ChartTemplate $chart): Response
    {
        $this->authorize('update', $chart);

        return Inertia::render('Charts/Form', [
            'chart' => [
                'id' => $chart->id,
                'name' => $chart->name,
                'description' => $chart->description,
                'model_type' => $chart->model_type,
                'chart_type' => $chart->chart_type,
                'group_by' => $chart->group_by,
                'group_period' => $chart->group_period,
                'aggregation' => $chart->aggregation,
                'metric_field' => $chart->metric_field,
                'filters' => $chart->filters ?? [],
                'from_date' => optional($chart->from_date)?->format('Y-m-d'),
                'to_date' => optional($chart->to_date)?->format('Y-m-d'),
                'show_on_dashboard' => $chart->show_on_dashboard,
                'sort_order' => $chart->sort_order,
            ],
            'catalog' => ReportFieldCatalog::inertiaCatalog(),
            'chartTypes' => $this->chartTypes(),
            'aggregations' => $this->aggregations(),
            'groupPeriods' => $this->groupPeriods(),
        ]);
    }

    public function update(Request $request, ChartTemplate $chart): RedirectResponse
    {
        $this->authorize('update', $chart);

        $chart->update($this->validated($request));

        return redirect()
            ->route('app.charts.index')
            ->with('success', 'Chart updated.');
    }

    public function destroy(ChartTemplate $chart): RedirectResponse
    {
        $this->authorize('delete', $chart);

        $chart->delete();

        return redirect()
            ->route('app.charts.index')
            ->with('success', 'Chart deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', ChartTemplate::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:chart_templates,id'],
        ]);

        ChartTemplate::query()->whereIn('id', $data['ids'])->delete();

        return back()->with('success', 'Charts deleted.');
    }

    private function validated(Request $request): array
    {
        $request->merge([
            'from_date' => $request->input('from_date') ?: null,
            'to_date' => $request->input('to_date') ?: null,
            'metric_field' => $request->input('metric_field') ?: null,
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'model_type' => ['required', 'string', 'in:'.implode(',', array_keys(ReportFieldCatalog::MODELS))],
            'chart_type' => ['required', 'string', 'in:bar,line,pie,doughnut'],
            'group_by' => ['required', 'string', 'max:255'],
            'group_period' => ['required', 'string', 'in:value,month,year'],
            'aggregation' => ['required', 'string', 'in:count,sum,avg'],
            'metric_field' => ['nullable', 'required_if:aggregation,sum,avg', 'string', 'max:255'],
            'filters' => ['nullable', 'array'],
            'filters.*.field' => ['nullable', 'string'],
            'filters.*.heading' => ['nullable', 'string', 'max:255'],
            'filters.*.filter_type' => ['nullable', 'string'],
            'filters.*.filter_value' => ['nullable'],
            'filters.*.filter_value_from' => ['nullable'],
            'filters.*.filter_value_to' => ['nullable'],
            'filters.*.filter_values' => ['nullable', 'array'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'show_on_dashboard' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['show_on_dashboard'] = $request->boolean('show_on_dashboard');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['filters'] = $data['filters'] ?? [];

        if ($data['aggregation'] === 'count') {
            $data['metric_field'] = null;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ChartTemplate $chart): array
    {
        return [
            'id' => $chart->id,
            'name' => $chart->name,
            'description' => $chart->description,
            'model_type' => $chart->model_type,
            'model_label' => ReportFieldCatalog::modelLabel($chart->model_type),
            'chart_type' => $chart->chart_type,
            'aggregation' => $chart->aggregation,
            'show_on_dashboard' => $chart->show_on_dashboard,
            'sort_order' => $chart->sort_order,
            'creator' => $chart->creator?->name,
            'created_at' => optional($chart->created_at)?->format('d M Y H:i'),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function chartTypes(): array
    {
        return [
            ['value' => 'bar', 'label' => 'Bar'],
            ['value' => 'line', 'label' => 'Line'],
            ['value' => 'pie', 'label' => 'Pie'],
            ['value' => 'doughnut', 'label' => 'Doughnut'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function aggregations(): array
    {
        return [
            ['value' => 'count', 'label' => 'Count records'],
            ['value' => 'sum', 'label' => 'Sum a field'],
            ['value' => 'avg', 'label' => 'Average a field'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function groupPeriods(): array
    {
        return [
            ['value' => 'value', 'label' => 'Each value'],
            ['value' => 'month', 'label' => 'By month (dates)'],
            ['value' => 'year', 'label' => 'By year (dates)'],
        ];
    }
}
