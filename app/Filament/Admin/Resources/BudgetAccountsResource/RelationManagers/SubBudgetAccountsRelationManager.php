<?php

namespace App\Filament\Admin\Resources\BudgetAccountsResource\RelationManagers;

use App\Models\BudgetTransactionHistory;
use App\Models\SubBudgetAccounts;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SubBudgetAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'subBudgetAccounts';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name'),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->visibleOn('create')
                    ->inputMode('decimal'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('MVR', locale: 'us'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('top_up_account')
                    ->label('Top Up Account')
                    ->color('success')
                    ->form([
                        Forms\Components\Repeater::make('subbudgets')
                            ->label('Sub Budgets')
                            ->grid(2)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->schema([
                                Forms\Components\TextInput::make('id')
                                    ->hidden(),
                                Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->disabled(),
                                Forms\Components\TextInput::make('name')
                                    ->label('Name')
                                    ->disabled(),
                                Forms\Components\Select::make('department_id')
                                    ->label('Department')
                                    ->relationship('department', 'name')
                                    ->disabled(),
                                Forms\Components\TextInput::make('amount')
                                    ->label('New Amount')
                                    ->required()
                                    ->numeric()
                                    ->inputMode('decimal'),
                            ])
                            ->default(fn ($livewire) => $livewire->ownerRecord->subBudgetAccounts->map(function ($subBudget) {
                                return [
                                    'id'    => $subBudget->id,
                                    'code'  => $subBudget->code,
                                    'name'  => $subBudget->name,
                                    'amount'=> $subBudget->amount,
                                ];
                            })->toArray()),
                    ])
                    ->action(function (array $data) {
                       
                        // Loop over the submitted subbudget data and update each record.
                        foreach ($data['subbudgets'] as $subBudgetData) {
                            BudgetTransactionHistory::createtransaction($subBudgetData['id'], 'Top UP', $subBudgetData['amount'], $subBudgetData['amount'], 'Funds added to account', Auth::id());
                            SubBudgetAccounts::find($subBudgetData['id'])
                                ->update([
                                    'amount' => $subBudgetData['amount'],
                                ]);
                        }
                        Notification::make()
                            ->title('Top Up Successful')
                            ->success()
                            ->send();
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
