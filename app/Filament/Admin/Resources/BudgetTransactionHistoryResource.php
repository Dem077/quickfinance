<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BudgetTransactionHistoryResource\Pages;
use App\Filament\Admin\Resources\BudgetTransactionHistoryResource\RelationManagers;
use App\Models\BudgetTransactionHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BudgetTransactionHistoryResource extends Resource
{
    protected static ?string $model = BudgetTransactionHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('sub_budget_id')
                    ->relationship('subBudget', 'name')
                    ->required(),
                Forms\Components\DatePicker::make('transaction_date')
                    ->required(),
                Forms\Components\TextInput::make('transaction_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('transaction_amount')
                    ->required()
                    ->money('MVR', locale: 'us')
                    ->numeric(),
                Forms\Components\TextInput::make('transaction_balance')
                    ->required()
                    ->money('MVR', locale: 'us')
                    ->numeric(),
                Forms\Components\Textarea::make('transaction_details')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('transaction_by')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subBudget.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subBudget.code')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_details')
                    ->searchable(),
                Tables\Columns\TextColumn::make('transaction_amount')
                    ->numeric()
                    ->money('MVR', locale: 'us')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_balance')
                    ->label('Account Balance')
                    ->numeric()
                    ->money('MVR', locale: 'us')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transactionBy.name')
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
                // Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListBudgetTransactionHistories::route('/'),
            'create' => Pages\CreateBudgetTransactionHistory::route('/create'),
            'edit' => Pages\EditBudgetTransactionHistory::route('/{record}/edit'),
        ];
    }
}
