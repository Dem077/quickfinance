<?php

namespace App\Filament\Admin\Resources\PurchaseOrdersResource\RelationManagers;

use App\Enums\PurchaseOrderStatus;
use App\Models\Item;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseOrderDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'PurchaseOrderDetails';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make()
                    ->columns(15)

                    ->schema([
                        Forms\Components\Select::make('desc')
                            ->label('Description')
                            ->options(function (Get $get, $state, $record) {
                                $prId = $this->ownerRecord->pr_id;
                                if (! $prId) {
                                    return [];
                                }

                                return PurchaseRequestDetails::where('pr_id', $prId)
                                    ->where('is_utilized', false)
                                    ->with('items')  // Eager load items relationship
                                    ->get()
                                    ->pluck('items.name', 'items.name')  // Use the correct relationship and column
                                    ->toArray();
                            })
                            ->live()
                            ->native(false)
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                if ($state) {
                                    $prItem = PurchaseRequestDetails::where('pr_id', $this->ownerRecord->pr_id)
                                        ->whereHas('items', function ($query) use ($state) {
                                            $query->where('name', $state);
                                        })
                                        ->with('items')
                                        ->first();

                                    if ($prItem) {
                                        $set('itemcode', $prItem->item->item_code);
                                        $set('unit_measure', $prItem->unit);
                                        $set('qty', $prItem->amount);
                                    }
                                }
                            })
                            ->columnSpan(2)
                            ->required(),
                        Forms\Components\TextInput::make('unit_measure')
                            ->label('Unit Measure')
                            ->required()
                            ->disabled()
                            ->maxLength(255)
                            ->dehydrated()
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('qty')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(2),
                        Forms\Components\Radio::make('gst')
                            ->label('GST(%)')
                            ->inline()
                            ->inlineLabel(false)
                            ->default(function (Get $get) {
                                if ((float) ($get('tax_amount') ?? 0) > 0) {
                                    return '8';
                                }

                                return '0';
                            })
                            ->columnSpan(2)
                            ->options([
                                '0' => '0%',
                                '8' => '8%', ])
                            ->required(),
                        Forms\Components\TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->numeric()
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('tax_amount')
                            ->label('GST')
                            ->numeric()
                            ->required()
                            ->reactive()
                            ->live()
                            ->disabled(function (Get $get) {
                                $gst = $get('gst');

                                return $gst === '0';
                            })
                            ->dehydrated()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                $qty = (float) ($get('qty') ?? 0);
                                $unitPrice = (float) ($get('unit_price') ?? 0);
                                $subtotal = $qty * $unitPrice;
                                $amount = $subtotal + (float) $state;
                                $set('amount', round($amount, 3));
                            })
                            ->columnSpan(2)
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', '')),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->disabled()
                            ->reactive()
                            ->live()
                            ->dehydrated()
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', ''))
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('calculate')
                                    ->label('Calculate')
                                    ->icon('heroicon-m-calculator')
                                    ->color('info')
                                    ->action(function (Get $get, Set $set, $state) {
                                        $qty = (float) ($get('qty') ?? 0);
                                        $unitPrice = (float) ($get('unit_price') ?? 0);
                                        $gst = $get('gst') ?? 0;
                                        $subtotal = $qty * $unitPrice;
                                        $gstamount = $subtotal * ($gst / 100);
                                        $amount = $subtotal + $gstamount;
                                        $set('tax_amount', round($gstamount, 3));
                                        $set('amount', round($amount, 3));
                                    }),
                            )
                            ->columnSpan(3),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('desc'),
                Tables\Columns\TextColumn::make('unit_measure'),
                Tables\Columns\TextColumn::make('qty'),
                Tables\Columns\TextColumn::make('unit_price'),
                Tables\Columns\TextColumn::make('tax_amount')
                    ->numeric()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->numeric(
                    )),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->numeric(
                    )),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {

                        $data['pr_id'] = $this->ownerRecord->pr_id;
                        $data['itemcode'] = Item::where('name', $data['desc'])->first()->item_code;
                        $data['budget_account_id'] = PurchaseRequestDetails::where('pr_id', $data['pr_id'])
                            ->whereHas('items', function ($query) use ($data) {
                                $query->where('name', $data['desc']);
                            })
                            ->first()
                            ->budget_account_id;
                        $item = Item::where('item_code', $data['itemcode'])->first()->id;
                        $pr = PurchaseRequests::where('id', $data['pr_id'])->first();
                        $pr->purchaseRequestDetails()->where('item_id', $item)->update(['is_utilized' => true]);

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record && $record->purchaseOrder->status == PurchaseOrderStatus::Draft),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        $item = Item::where('item_code', $record->itemcode)->first()->id;
                        $pr = PurchaseRequests::where('id', $record->purchaseOrder->pr_id)->first();
                        $pr->purchaseRequestDetails()->where('item_id', $item)->update(['is_utilized' => false]);
                    })
                    ->visible(fn ($record) => $record && $record->purchaseOrder->status == PurchaseOrderStatus::Draft),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }
}
