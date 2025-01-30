<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;
use App\Models\PurchaseRequests;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseRequestsResource extends Resource
{
    protected static ?string $model = PurchaseRequests::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('pr_no')
                    ->disabled()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('date')
                    ->date()
                    ->required(),
                Forms\Components\Select::make('department_id')
                    ->relationship('department', 'name')
                    ->required(),
                Forms\Components\Select::make('budget_account_id')
                    ->relationship('budgetAccount', 'name', function ($query) {
                        $query->selectRaw("CONCAT(code, ' - ', budget_accounts.name, ' (', budget_accounts.expenditure_type, ' - ', budget_accounts.account, ')') AS display_name, budget_accounts.id")
                              ->join('budget_accounts', 'sub_budget_accounts.budget_account_id', '=', 'budget_accounts.id');
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->searchable(['budget_accounts.name', 'code', 'budget_accounts.expenditure_type', 'budget_accounts.account'])
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pr_no')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                    Tables\Columns\TextColumn::make('budget_account_id')
                    ->label('Budget Code')
                    ->getStateUsing(fn ($record) => $record->budgetAccount->code ?? '-')
                    ->searchable(['budget_accounts.name', 'budget_accounts.code', 'budget_accounts.expenditure_type', 'budget_accounts.account']),
                Tables\Columns\TextColumn::make('department.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseRequests::route('/'),
            'create' => Pages\CreatePurchaseRequests::route('/create'),
            'edit' => Pages\EditPurchaseRequests::route('/{record}/edit'),
        ];
    }
}
