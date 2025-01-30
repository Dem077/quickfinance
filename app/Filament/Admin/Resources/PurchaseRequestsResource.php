<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;
use App\Filament\Admin\Resources\PurchaseRequestsResource\RelationManagers;
use App\Models\PurchaseRequests;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PurchaseRequestsResource extends Resource
{
    protected static ?string $model = PurchaseRequests::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('pr_no')
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->disabled()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('date')
                    ->native(false)
                    ->closeOnDateSelection()
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
                    ->preload()
                    ->required(),
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => Auth::id())
                    ->required(),
                Section::make('Items')
                    ->schema([
                        Forms\Components\Repeater::make('purchaseOrderDetails')
                        ->schema([
                            Forms\Components\Grid::make()
                                ->columns(7)
                                ->schema([
                                    Forms\Components\TextInput::make('item')
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpan(2),
                                    Forms\Components\TextInput::make('unit')
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpan(3),
                                    Forms\Components\TextInput::make('amount')
                                        ->required()
                                        ->numeric()
                                        ->columnSpan(2),
                                ])
                        ])
                        ->required(fn (string $operation): bool => $operation === 'create')
                        
                        ->minItems(1),
                    ])->hidden(fn (string $operation): bool => $operation === 'edit')
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
            RelationManagers\PurchaseRequestDetailsRelationManager::class,
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
