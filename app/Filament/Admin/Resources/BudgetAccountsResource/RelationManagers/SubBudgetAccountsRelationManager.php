<?php

namespace App\Filament\Admin\Resources\BudgetAccountsResource\RelationManagers;

use App\Models\BudgetTransactionHistory;
use App\Models\SubBudgetAccounts;
use App\Models\SubBudgetDepartmentAllocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SubBudgetAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'subBudgetAccounts';

    public function form(Form $form): Form
    {
        return $form
            ->columns(3)
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->columnSpan(1)
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->columnSpan(1)
                    ->maxLength(255),
                Forms\Components\TextInput::make('display_name')
                    ->label('Display Name')
                    ->columnSpan(1)
                    ->maxLength(255),
                Forms\Components\Repeater::make('allocations')
                    ->label('Department Allocations')
                    ->relationship('allocations')
                    ->columns(3)
                    ->columnSpanFull()
                    ->minItems(1)
                    ->schema([
                        Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->relationship('department', 'name')
                            ->required()
                            ->searchable()
                            ->native(false),
                        Forms\Components\Select::make('location_id')
                            ->label('Location')
                            ->relationship('location', 'name')
                            ->searchable()
                            ->native(false),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->inputMode('decimal'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Display Name'),
                Tables\Columns\TextColumn::make('allocations_list')
                    ->label('Allocations')
                    ->bulleted()
                    ->state(fn (SubBudgetAccounts $record) => $record->allocations
                        ->loadMissing('department', 'location')
                        ->map(fn ($allocation) => ($allocation->department?->name ?? 'Department').
                            ($allocation->location ? ' / '.$allocation->location->name : '').
                            ': '.number_format($allocation->amount, 2)
                        )
                        ->values()
                        ->all())
                    ->wrap(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('MVR'),
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
                        Forms\Components\Repeater::make('allocations')
                            ->label('Allocations')
                            ->grid(2)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->schema([
                                Forms\Components\TextInput::make('id')
                                    ->hidden(),
                                Forms\Components\TextInput::make('sub_budget_account_id')
                                    ->hidden(),
                                Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->disabled(),
                                Forms\Components\TextInput::make('name')
                                    ->label('Sub Budget')
                                    ->disabled(),
                                Forms\Components\TextInput::make('department')
                                    ->label('Department')
                                    ->disabled(),
                                Forms\Components\TextInput::make('location')
                                    ->label('Location')
                                    ->disabled(),
                                Forms\Components\TextInput::make('amount')
                                    ->label('New Amount')
                                    ->required()
                                    ->numeric()
                                    ->inputMode('decimal'),
                            ])
                            ->default(function ($livewire) {
                                return $livewire->ownerRecord
                                    ->subBudgetAccounts()
                                    ->with('allocations.department', 'allocations.location')
                                    ->get()
                                    ->flatMap(function (SubBudgetAccounts $subBudget) {
                                        return $subBudget->allocations->map(function ($allocation) use ($subBudget) {
                                            return [
                                                'id' => $allocation->id,
                                                'sub_budget_account_id' => $subBudget->id,
                                                'code' => $subBudget->code,
                                                'name' => $subBudget->name,
                                                'department' => $allocation->department?->name,
                                                'location' => $allocation->location?->name,
                                                'amount' => $allocation->amount,
                                            ];
                                        });
                                    })
                                    ->values()
                                    ->toArray();
                            }),
                    ])
                    ->action(function (array $data) {
                        $allocations = collect($data['allocations'] ?? []);

                        $allocations->each(function (array $row) {
                            if (! empty($row['id'])) {
                                SubBudgetDepartmentAllocation::find($row['id'])
                                    ?->update(['amount' => $row['amount']]);
                            }
                        });

                        $allocations
                            ->groupBy('sub_budget_account_id')
                            ->each(function (Collection $group, $subBudgetId) {
                                $total = (int) $group->sum('amount');
                                BudgetTransactionHistory::createtransaction(
                                    $subBudgetId,
                                    'Top UP',
                                    $total,
                                    $total,
                                    'Funds added to account',
                                    Auth::id()
                                );
                            });

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
