<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\App\Concerns\HandlesSimpleCrud;
use App\Http\Controllers\Controller;
use App\Models\Departments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DepartmentController extends Controller
{
    use HandlesSimpleCrud;

    protected function modelClass(): string
    {
        return Departments::class;
    }

    protected function inertiaPrefix(): string
    {
        return 'Departments';
    }

    protected function routePrefix(): string
    {
        return 'app.departments';
    }

    protected function searchableColumns(): array
    {
        return ['name'];
    }

    protected function query(): Builder
    {
        return Departments::query()->with('user:id,name');
    }

    protected function validationRules(?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'petty_cash_float_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function transform(Model $model): array
    {
        return [
            'name' => $model->name,
            'petty_cash_float_amount' => $model->petty_cash_float_amount,
            'hod_name' => $model->user?->name,
        ];
    }
}
