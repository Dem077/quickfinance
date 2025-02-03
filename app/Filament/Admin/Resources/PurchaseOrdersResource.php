<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseOrdersResource\Pages;
use App\Filament\Admin\Resources\PurchaseOrdersResource\RelationManagers;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Tables;
use Filament\Tables\Table;
use Laravel\SerializableClosure\Serializers\Native;

class PurchaseOrdersResource extends Resource
{
    protected static ?string $model = PurchaseOrders::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('po_no')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('vendor_id')
                    ->relationship('vendor', 'name')
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),
                Forms\Components\Select::make('pr_id')
                    ->relationship('purchaseRequest', 'pr_no', function ($query) {
                        return $query->whereNotNull('uploaded_document');
                    })
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $set('purchaseRequestDetails.*.pr_id', $state);
                    })
                    ->required(),
                    Select::make('payment_method')
                        ->label('Payment Method')
                        ->native(false)
                        ->options([
                            'purchase_order' => 'Purchase Order',
                            'petty_cash' => 'Petty Cash',
                        ])
                        ->default('purchase_order')
                        ->required(),
                Section::make('Item to be Utilized')
                    ->schema([
                        Forms\Components\Repeater::make('purchaseOrderDetails')
                            ->label('Items / Services')
                            ->extraItemActions([
                                Forms\Components\Actions\Action::make('calculate_all')
                                    ->label('Generate Total')
                                    ->icon('heroicon-m-calculator')
                                    ->color('info')
                                    ->tooltip('Generate Total Amount')
                                    ->action(function (Get $get, Set $set) {
                                        $items = $get('purchaseOrderDetails');
                                        if (!is_array($items)) return;
                                        
                                        foreach ($items as $key => $item) {
                                            $qty = (float) ($item['qty'] ?? 0);
                                            $unitPrice = (float) ($item['unit_price'] ?? 0);
                                            $set("purchaseOrderDetails.{$key}.amount", $qty * $unitPrice);
                                        }
                                    })
                            ])
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->columns(12)
                                    ->schema([
                                        Forms\Components\TextInput::make('itemcode')
                                            ->label('Item Code')
                                            ->required()
                                            ->disabled()
                                            ->live()
                                            ->dehydrated(false)
                                            ->columnSpan(2),
                                        Forms\Components\Select::make('desc')
                                            ->label('Description')
                                            ->options(function (Get $get, $state, $record) {
                                                $prId = $get('../../pr_id');
                                                if (!$prId) return [];
                                                
                                                $details = collect($get('../../purchaseRequestDetails') ?? []);
                                                $currentIndex = $details->search(function ($item) use ($state) {
                                                    return $item['desc'] === $state;
                                                });
                                                
                                                $selectedItems = $details
                                                    ->filter(function ($item, $index) use ($currentIndex) {
                                                        return $index !== $currentIndex;
                                                    })
                                                    ->pluck('desc')
                                                    ->filter()
                                                    ->toArray();
                                                
                                                return PurchaseRequestDetails::where('pr_id', $prId)
                                                    ->whereNotIn('item_id', $selectedItems)
                                                    ->with('item')  // Eager load items relationship
                                                    ->get()
                                                    ->pluck('item.name', 'item.name')  // Use the correct relationship and column
                                                    ->toArray();
                                            })
                                            ->live()
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                if ($state) {
                                                    $prItem = PurchaseRequestDetails::where('pr_id', $get('../../pr_id'))
                                                        ->whereHas('item', function ($query) use ($state) {
                                                            $query->where('name', $state);
                                                        })
                                                        ->with('item')
                                                        ->first();
                                                    
                                                    if ($prItem) {
                                                        $set('unit_measure', $prItem->unit);
                                                        $set('qty', $prItem->amount);
                                                        $set('itemcode', $prItem->item->item_code);
                                                    }
                                                }
                                            })
                                            ->disabled(fn (Get $get) => !$get('../../pr_id'))
                                            ->required(),
                                        Forms\Components\TextInput::make('unit_measure')
                                            ->label('Unit Measure')
                                            ->required()
                                            ->disabled()
                                            ->maxLength(255)
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('qty')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->required()
                                            ->disabled()
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->required()
                                            ->columnSpan(2),
                                        
                                        Forms\Components\TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->required()
                                            ->disabled()
                                            ->columnSpan(2),
                                    ]),
                            ])
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minItems(1),
                ])->hidden(fn (string $operation): bool => $operation === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendor.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('po_no')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pr_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PurchaseOrderDetailsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrders::route('/create'),
            'edit' => Pages\EditPurchaseOrders::route('/{record}/edit'),
        ];
    }
}
