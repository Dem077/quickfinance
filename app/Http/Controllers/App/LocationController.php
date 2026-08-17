<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\App\Concerns\HandlesSimpleCrud;
use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

class LocationController extends Controller
{
    use HandlesSimpleCrud;

    protected function modelClass(): string
    {
        return Location::class;
    }

    protected function inertiaPrefix(): string
    {
        return 'Locations';
    }

    protected function routePrefix(): string
    {
        return 'app.locations';
    }

    protected function searchableColumns(): array
    {
        return ['name'];
    }

    protected function validationRules(?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function transform(Model $model): array
    {
        return [
            'name' => $model->name,
        ];
    }
}
