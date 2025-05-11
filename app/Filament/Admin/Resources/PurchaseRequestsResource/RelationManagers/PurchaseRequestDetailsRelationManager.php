<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\RelationManagers;

use App\Models\SubBudgetAccounts;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PurchaseRequestDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseRequestDetails';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make()
                    ->columns(12)
                    ->schema([
                        Forms\Components\Select::make('item_id')
                            ->relationship('items', 'name')
                            ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests') || Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'))
                            ->required()
                            ->searchable()
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('unit')
                            ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests') || Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\Select::make('budget_account_id')
                            ->label('Budget Account')
                            ->options(function () {
                                return \App\Models\SubBudgetAccounts::with('department')
                                    ->get()
                                    ->mapWithKeys(function ($row) {
                                        return [
                                            $row->id => $row->code.' - '.$row->name.
                                                ($row->department ? ' ('.$row->department->name.
                                                (isset($row->location) ? ' / '.$row->location->name : '').')' : ''),
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->searchable()
                            ->disabled(fn ($record) => Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests') && ! Auth::user()->can('approve_purchase::requests'))
                            ->required()
                            ->columnSpan(4),
                        Forms\Components\TextInput::make('amount')
                            ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests') || Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'))
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('est_cost')
                            ->disabled(fn ($record) => Auth::user()->can('approve_purchase::requests') || Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'))
                            ->required()
                            ->numeric()
                            ->reactive()
                            ->rules([
                                fn (Forms\Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                    if (empty($value)) {
                                        return;
                                    }
                                    $budgetAccountId = $get('budget_account_id');
                                    if ($budgetAccountId) {
                                        $account = SubBudgetAccounts::find($budgetAccountId);
                                        if ($account && $value > $account->amount) {
                                            $fail("You don't have enough funds for this budget code.");
                                        }
                                    }
                                },
                            ])
                            ->columnSpan(2),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('desc')
            ->columns([
                Tables\Columns\TextColumn::make('items.name')->label('Item Name'),
                Tables\Columns\TextColumn::make('unit'),
                Tables\Columns\TextColumn::make('budgetAccount.code')->label('Budget Account'),
                Tables\Columns\TextColumn::make('amount')->label('Quantity'),
                Tables\Columns\TextColumn::make('est_cost')->label('Estimated Cost'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Item')
                    ->visible(fn ($record) => Auth::user()->can('send_approval_purchase::requests')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->recordUrl(false)
            ->bulkActions([
            ]);
    }
}
