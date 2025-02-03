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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseOrderDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'PurchaseOrderDetails';

    public function form(Form $form): Form
    {
        return $form
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
                                $prId = $this->ownerRecord->pr_id;
                                if (!$prId) return [];
                                
                                return PurchaseRequestDetails::where('pr_id', $prId)
                                    ->whereHas('item')
                                    ->with('item')
                                    ->get()
                                    ->pluck('item.name', 'item.name')
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
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('calculate')
                            ->label('Calculate')
                            ->icon('heroicon-m-calculator')
                            ->color('info')
                            ->action(function (Get $get, Set $set) {
                                $qty = (float) ($get('qty') ?? 0);
                                $unitPrice = (float) ($get('unit_price') ?? 0);
                                $set('amount', $qty * $unitPrice);
                            }),)
                            ->columnSpan(2),
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
