<?php

namespace App\Filament\Forms;

use App\Models\AssetReceipt;
use App\Services\SnipeIt\SnipeItService;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Illuminate\Support\Collection;

class SnipeItAccessoryForm
{
    public static function schema(?AssetReceipt $receipt = null): array
    {
        $snipeIt = app(SnipeItService::class);

        if (! $snipeIt->isEnabled()) {
            return [
                Forms\Components\Placeholder::make('snipe_disabled')
                    ->label('Snipe-IT')
                    ->content('Snipe-IT is not configured. Add SNIPE_IT_URL and SNIPE_IT_API_TOKEN to your .env file.'),
            ];
        }

        $receipt?->loadMissing(['purchaseOrder', 'purchaseOrderDetail', 'item']);
        $defaultQty = $receipt?->snipe_quantity ?? $receipt?->purchaseOrderDetail?->assetLineQuantity() ?? 1;

        return [
            Forms\Components\Section::make('Snipe-IT Accessory')
                ->description('Creates one accessory record in Snipe-IT with the PO line quantity (not separate units like assets).')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Accessory Name')
                        ->required()
                        ->maxLength(255)
                        ->default(fn () => $receipt?->name ?? $receipt?->asset_description ?? $receipt?->item?->name)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('snipe_category_id')
                        ->label('Category')
                        ->options(fn () => $snipeIt->categoryOptions())
                        ->default(config('snipe-it.default_accessory_category_id'))
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Snipe-IT accessory category (like model for assets).'),
                    Forms\Components\TextInput::make('snipe_quantity')
                        ->label('Quantity')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->default($defaultQty)
                        ->helperText('Total quantity received for this PO line.'),
                    Forms\Components\Select::make('snipe_location_id')
                        ->label('Location')
                        ->options(fn () => $snipeIt->locationOptions())
                        ->native(false)
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('order_number')
                        ->label('Order Number')
                        ->maxLength(255)
                        ->default(fn () => $receipt?->order_number ?? $receipt?->invoice_number ?? $receipt?->purchaseOrder?->po_no),
                    Forms\Components\DatePicker::make('purchase_date')
                        ->label('Purchase Date')
                        ->native(false)
                        ->default(fn () => $receipt?->purchase_date ?? $receipt?->purchaseOrder?->date),
                    Forms\Components\TextInput::make('purchase_cost')
                        ->label('Purchase Cost')
                        ->numeric()
                        ->prefix('MVR')
                        ->default(fn () => $receipt?->purchase_cost ?? $receipt?->purchaseOrderDetail?->amount),
                    Forms\Components\Select::make('snipe_supplier_id')
                        ->label('Supplier')
                        ->options(fn () => $snipeIt->supplierOptions())
                        ->native(false)
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('model_number')
                        ->label('Model Number')
                        ->maxLength(255)
                        ->default(fn () => $receipt?->model_number),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function defaultFill(AssetReceipt $receipt): array
    {
        return [
            'name' => $receipt->name ?? $receipt->asset_description ?? $receipt->item?->name,
            'snipe_category_id' => $receipt->snipe_category_id ?? config('snipe-it.default_accessory_category_id'),
            'snipe_quantity' => $receipt->snipe_quantity ?? $receipt->purchaseOrderDetail?->assetLineQuantity() ?? 1,
            'snipe_location_id' => $receipt->snipe_location_id,
            'snipe_supplier_id' => $receipt->snipe_supplier_id,
            'order_number' => $receipt->order_number ?? $receipt->invoice_number ?? $receipt->purchaseOrder?->po_no,
            'purchase_date' => $receipt->purchase_date ?? $receipt->purchaseOrder?->date,
            'purchase_cost' => $receipt->purchase_cost ?? $receipt->purchaseOrderDetail?->amount,
            'model_number' => $receipt->model_number,
            'notes' => $receipt->notes,
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function bulkReceiveSchema(): array
    {
        $snipeIt = app(SnipeItService::class);

        if (! $snipeIt->isEnabled()) {
            return [
                Forms\Components\Placeholder::make('snipe_disabled')
                    ->label('Snipe-IT')
                    ->content('Snipe-IT is not configured.'),
            ];
        }

        return [
            Forms\Components\Section::make('Shared accessory details')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('snipe_category_id')
                        ->label('Category')
                        ->options(fn () => $snipeIt->categoryOptions())
                        ->default(config('snipe-it.default_accessory_category_id'))
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('snipe_location_id')
                        ->label('Location')
                        ->options(fn () => $snipeIt->locationOptions())
                        ->native(false)
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('order_number')
                        ->label('Order Number')
                        ->maxLength(255),
                    Forms\Components\DatePicker::make('purchase_date')
                        ->label('Purchase Date')
                        ->native(false),
                    Forms\Components\Select::make('snipe_supplier_id')
                        ->label('Supplier')
                        ->options(fn () => $snipeIt->supplierOptions())
                        ->native(false)
                        ->searchable()
                        ->preload(),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Accessory lines')
                ->schema([
                    Forms\Components\Repeater::make('accessories')
                        ->label('')
                        ->schema([
                            Forms\Components\Hidden::make('asset_receipt_id'),
                            Forms\Components\TextInput::make('line_label')
                                ->label('PO line')
                                ->disabled()
                                ->dehydrated(true)
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('name')
                                ->label('Accessory Name')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('snipe_quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->required()
                                ->minValue(1),
                            Forms\Components\TextInput::make('purchase_cost')
                                ->label('Purchase Cost')
                                ->numeric()
                                ->prefix('MVR'),
                        ])
                        ->columns(2)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ]),
        ];
    }

    /**
     * @param  Collection<int, AssetReceipt>  $records
     * @return array<string, mixed>
     */
    public static function bulkDefaultFill(Collection $records): array
    {
        $first = $records->first();
        $shared = self::defaultFill($first);

        unset($shared['name'], $shared['snipe_quantity'], $shared['purchase_cost']);

        $shared['accessories'] = $records->map(function (AssetReceipt $receipt): array {
            $fill = self::defaultFill($receipt);

            return [
                'asset_receipt_id' => $receipt->id,
                'line_label' => $receipt->item?->name.' — Qty '.($fill['snipe_quantity'] ?? 1),
                'name' => $fill['name'],
                'snipe_quantity' => $fill['snipe_quantity'],
                'purchase_cost' => $fill['purchase_cost'],
            ];
        })->all();

        return $shared;
    }
}
