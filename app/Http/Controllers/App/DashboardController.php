<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ChartTemplate;
use App\Services\ChartGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ChartGenerator $charts): Response
    {
        $user = $request->user();
        $dashboardCharts = [];
        $canManageCharts = false;
        $canCreateCharts = false;

        if ($user && $user->can('viewAny', ChartTemplate::class)) {
            $dashboardCharts = ChartTemplate::query()
                ->where('show_on_dashboard', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (ChartTemplate $chart) => $charts->series($chart))
                ->values()
                ->all();

            $canManageCharts = true;
            $canCreateCharts = $user->can('create', ChartTemplate::class);
        }

        return Inertia::render('Dashboard', [
            'charts' => $dashboardCharts,
            'canManageCharts' => $canManageCharts,
            'canCreateCharts' => $canCreateCharts,
        ]);
    }
}
