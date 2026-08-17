<?php

namespace App\Http\Controllers\App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

trait HandlesSimpleCrud
{
    abstract protected function modelClass(): string;

    abstract protected function inertiaPrefix(): string;

    abstract protected function routePrefix(): string;

    abstract protected function searchableColumns(): array;

    abstract protected function validationRules(?Model $model = null): array;

    /**
     * @return array<string, mixed>
     */
    protected function transform(Model $model): array
    {
        return $model->only($model->getFillable());
    }

    /**
     * @return array<string, mixed>
     */
    protected function formProps(?Model $model = null): array
    {
        return [];
    }

    protected function query(): Builder
    {
        return ($this->modelClass())::query();
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', $this->modelClass());

        $search = $request->string('search')->trim()->toString();
        $columns = $this->searchableColumns();

        $records = $this->query()
            ->when($search !== '' && $columns !== [], function (Builder $query) use ($search, $columns): void {
                $query->where(function (Builder $query) use ($search, $columns): void {
                    foreach ($columns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->{$method}($column, 'like', "%{$search}%");
                    }
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Model $model): array => array_merge(
                ['id' => $model->id],
                $this->transform($model)
            ));

        return Inertia::render($this->inertiaPrefix().'/Index', [
            'records' => $records,
            'filters' => ['search' => $search],
            'can' => [
                'create' => $request->user()->can('create', $this->modelClass()),
                'deleteAny' => $request->user()->can('deleteAny', $this->modelClass()),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', $this->modelClass());

        return Inertia::render($this->inertiaPrefix().'/Form', [
            'record' => null,
            ...$this->formProps(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', $this->modelClass());

        ($this->modelClass())::create($request->validate($this->validationRules()));

        return redirect()
            ->route($this->routePrefix().'.index')
            ->with('success', 'Record created.');
    }

    public function edit(int $id): Response
    {
        $model = $this->query()->findOrFail($id);
        $this->authorize('update', $model);

        return Inertia::render($this->inertiaPrefix().'/Form', [
            'record' => array_merge(['id' => $model->id], $this->transform($model)),
            ...$this->formProps($model),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $model = $this->query()->findOrFail($id);
        $this->authorize('update', $model);

        $model->update($request->validate($this->validationRules($model)));

        return redirect()
            ->route($this->routePrefix().'.index')
            ->with('success', 'Record updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $model = $this->query()->findOrFail($id);
        $this->authorize('delete', $model);
        $model->delete();

        return redirect()
            ->route($this->routePrefix().'.index')
            ->with('success', 'Record deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', $this->modelClass());

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $this->query()->whereIn('id', $data['ids'])->delete();

        return redirect()
            ->route($this->routePrefix().'.index')
            ->with('success', count($data['ids']).' record(s) deleted.');
    }
}
