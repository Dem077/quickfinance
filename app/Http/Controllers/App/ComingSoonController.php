<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ComingSoonController extends Controller
{
    public function __invoke(string $module): Response
    {
        $titles = [
            'items' => 'Items',
            'vendors' => 'Vendors',
            'departments' => 'Departments',
            'locations' => 'Locations',
            'projects' => 'Projects',
            'purchase-requests' => 'Purchase Requests',
            'procure' => 'Procure',
            'petty-cash' => 'Petty Cash',
            'asset-management' => 'Asset Management',
            'budgets' => 'Budgets',
            'reports' => 'Reports',
        ];

        return Inertia::render('ComingSoon', [
            'module' => $module,
            'title' => $titles[$module] ?? str($module)->headline()->toString(),
        ]);
    }
}
