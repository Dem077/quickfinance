<?php

namespace App\Filament\Admin\Resources\PurchaseOrdersResource\RelationManagers;

use App\Models\PurchaseRequestDetails;
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
                        Forms\Components\TextInput::make('itemcode')
                            ->label('Item Code')
                            ->required()
                            ->disabled()
                            ->live()
                            ->dehydrated(false)
                            ->columnSpan(2),
                        Forms\Components\Select::make('desc')
                            ->label('Description')                            
                            ->disabled(fn (string $operation): bool => $operation != 'edit')
                            ->options(function (Get $get, $state, $record) {
                                $prId = $this->ownerRecord->pr_id;
                                if (! $prId) {
                                    return [];
                                }
                                return PurchaseRequestDetails::where('pr_id', $prId)
                                    
                                    ->with('items')  // Eager load items relationship
                                    ->get()
                                    ->pluck('items.name', 'items.name')  // Use the correct relationship and column
                                    ->toArray();
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                if ($state) {
                                    $prItem = PurchaseRequestDetails::where('pr_id', $this->ownerRecord->pr_id)
                                        ->whereHas('item', function ($query) use ($state) {
                                            $query->where('name', $state);
                                        })
                                        ->with('item')
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
                            ->disabled(fn (string $operation): bool => $operation != 'edit')
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('qty')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->disabled(fn (string $operation): bool => $operation != 'edit')
                            ->columnSpan(2),
                        Forms\Components\Radio::make('gst')
                            ->label('GST(%)')
                            ->inline()
                            ->inlineLabel(false)
                            ->default('8')
                            ->columnSpan(2)
                            ->options([
                                '0' => '0%',
                                '8' => '8%', ])
                            ->required(),
                        Forms\Components\TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->numeric()
                            ->disabled(fn (string $operation): bool => $operation != 'edit')
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('calculate')
                                    ->label('Calculate')
                                    ->icon('heroicon-m-calculator')
                                    ->color('info')
                                    ->action(function (Get $get, Set $set) { 
                                        $qty = (float) ($get('qty') ?? 0);
                                        $gst = (float) ($get('gst') ?? 0);
                                        $unitPrice = (float) ($get('unit_price') ?? 0);
                                        $set('amount', $qty * $unitPrice + ($qty * $unitPrice * ($gst / 100)));
                                    }), )
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
                Tables\Columns\TextColumn::make('amount'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $data['pr_id'] = $this->ownerRecord->pr_id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
