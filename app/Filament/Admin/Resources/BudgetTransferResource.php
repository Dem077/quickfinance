<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BudgetTransferResource\Pages;
use App\Filament\Admin\Resources\BudgetTransferResource\RelationManagers;
use App\Models\BudgetTransfer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class BudgetTransferResource extends Resource
{
    protected static ?string $model = BudgetTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-end-on-rectangle';

    protected static ?string $navigationGroup = 'Record Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('from_budget_id')
                    ->relationship('fromBudget', 'name')
                    ->getOptionLabelFromRecordUsing(
                        fn($record) => $record->department_id
                            ? "{$record->name} - {$record->department->name} ({$record->code})"
                            : "{$record->name} ({$record->code})"
                    )
                    ->required(),
                Forms\Components\Select::make('to_budget_id')
                    ->relationship('toBudget', 'name')
                    ->getOptionLabelFromRecordUsing(
                        fn($record) => $record->department_id
                        ? "{$record->name} - {$record->department->name} ({$record->code})"
                        : "{$record->name} ({$record->code})"
                    )
                    ->required(),
                Forms\Components\Hidden::make('user_id')
                    ->default(Auth::id())
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Forms\Components\Textarea::make('description')
                    ->label('Reason')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fromBudget.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('toBudget.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->money('MVR', locale: 'us')
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
            ])
            ->recordUrl(false)
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
            'index' => Pages\ListBudgetTransfers::route('/'),
            'create' => Pages\CreateBudgetTransfer::route('/create')
        ];
    }
}
