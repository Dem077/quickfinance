<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\App\Concerns\HandlesSimpleCrud;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

class ProjectController extends Controller
{
    use HandlesSimpleCrud;

    protected function modelClass(): string
    {
        return Project::class;
    }

    protected function inertiaPrefix(): string
    {
        return 'Projects';
    }

    protected function routePrefix(): string
    {
        return 'app.projects';
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
