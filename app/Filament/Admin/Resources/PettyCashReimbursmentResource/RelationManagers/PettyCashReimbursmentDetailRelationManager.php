<?php

namespace App\Filament\Admin\Resources\PettyCashReimbursmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PettyCashReimbursmentDetailRelationManager extends RelationManager
{
    protected static string $relationship = 'pettyCashReimbursmentDetails';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required()
                    ->columnSpan(1),
                Forms\Components\Select::make('Vendor_id')
                    ->relationship('Vendor', 'name')
                    ->native(false)
                    ->columnSpan(2)
                    ->required(),
                Forms\Components\TextInput::make('bill_no')
                    ->columnSpan(2)
                    ->required(),
                Forms\Components\TextInput::make('details')
                    ->columnSpan(2)
                    ->required(),
                Forms\Components\Select::make('sub_budget_id')
                    ->relationship('SubBudget', 'name')
                    ->native(false)
                    ->columnSpan(2)
                    ->nullable(),
                Forms\Components\Select::make('po_id')
                    ->relationship('PurchaseOrder', 'po_no')
                    ->native(false)
                    ->columnSpan(2)
                    ->nullable(),
                
                Forms\Components\TextInput::make('amount')
                    ->columnSpan(1)
                    ->minValue(1)
                    ->required()
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date('d-m-Y'),
                Tables\Columns\TextColumn::make('vendor.name'),
                Tables\Columns\TextColumn::make('details'),
                Tables\Columns\TextColumn::make('bill_no'),
                Tables\Columns\TextColumn::make('details'),
                Tables\Columns\TextColumn::make('subBudget.code'),
                Tables\Columns\TextColumn::make('purchaseOrder.po_no'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('MVR', locale: 'us'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
