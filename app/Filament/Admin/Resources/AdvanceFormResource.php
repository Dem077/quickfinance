<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AdvanceFormResource\Pages;
use App\Models\AdvanceForm;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdvanceFormResource extends Resource
{
    protected static ?string $model = AdvanceForm::class;

    protected static ?string $navigationGroup = 'Record Management';

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('qoation_no')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('expected_delivery')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('request_number')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('advance_percentage')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('advance_amount')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('balance_amount')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('generated_by')
                    ->required()
                    ->relationship('user', 'name'),
                Forms\Components\Select::make('purchase_order_id')
                    ->required()
                    ->relationship('purchaseOrder', 'po_no'),
                Forms\Components\Select::make('vendors_id')
                    ->relationship('vendor', 'name')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendor.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('qoation_no')
                    ->searchable(),
                Tables\Columns\TextColumn::make('expected_delivery')
                    ->suffix(' Days')
                    ->searchable(),
                Tables\Columns\TextColumn::make('request_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('advance_percentage')
                    ->searchable(),
                Tables\Columns\TextColumn::make('advance_amount')
                    ->searchable(),
                Tables\Columns\TextColumn::make('balance_amount')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdvanceForms::route('/'),
            'create' => Pages\CreateAdvanceForm::route('/create'),
            'edit' => Pages\EditAdvanceForm::route('/{record}/edit'),
        ];
    }
}
