<?php

namespace App\Http\Controllers\App;

use App\Enums\ItemTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Item::class);

        $search = $request->string('search')->trim()->toString();

        $items = Item::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('item_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('item_code')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Item $item): array => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'name' => $item->name,
                'type' => $item->type?->value,
                'type_label' => $item->type?->getLabel(),
            ]);

        return Inertia::render('Items/Index', [
            'items' => $items,
            'filters' => ['search' => $search],
            'can' => [
                'create' => $request->user()->can('create', Item::class),
                'deleteAny' => $request->user()->can('deleteAny', Item::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Item::class);

        return Inertia::render('Items/Form', [
            'item' => null,
            'types' => $this->typeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Item::class);

        $data = $this->validated($request);

        Item::create($data);

        return redirect()
            ->route('app.items.index')
            ->with('success', 'Item created.');
    }

    public function edit(Item $item): Response
    {
        $this->authorize('update', $item);

        return Inertia::render('Items/Form', [
            'item' => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'name' => $item->name,
                'type' => $item->type?->value ?? ItemTypeEnum::Other->value,
            ],
            'types' => $this->typeOptions(),
        ]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $item->update($this->validated($request));

        return redirect()
            ->route('app.items.index')
            ->with('success', 'Item updated.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $item->delete();

        return redirect()
            ->route('app.items.index')
            ->with('success', 'Item deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', Item::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:items,id'],
        ]);

        Item::query()->whereIn('id', $data['ids'])->delete();

        return redirect()
            ->route('app.items.index')
            ->with('success', count($data['ids']).' item(s) deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'item_code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:'.implode(',', array_column(ItemTypeEnum::cases(), 'value'))],
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function typeOptions(): array
    {
        return collect(ItemTypeEnum::cases())
            ->map(fn (ItemTypeEnum $type): array => [
                'value' => $type->value,
                'label' => $type->getLabel() ?? $type->value,
            ])
            ->values()
            ->all();
    }
}
