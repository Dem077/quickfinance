<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\RelationManagers;

use App\Enums\UnitsEnum;
use App\Support\DepartmentBudgetOptions;
use App\Support\PurchaseRequestBudget;
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
        $ownerRequestUserId = $this->getOwnerRecord()?->user_id;

        return $form
            ->schema([
                Forms\Components\Grid::make()
                    ->columns(12)
                    ->schema([
                        Forms\Components\Select::make('item_id')
                            ->relationship('items', 'name')
                            ->disabled(function ($record) use ($ownerRequestUserId): bool {
                                $requestOwnerId = $record?->purchaseRequest?->user_id ?? $ownerRequestUserId;

                                return ($requestOwnerId != Auth::id() && Auth::user()->can('approve_purchase::requests'))
                                    || (Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'));
                            })
                            ->required()
                            ->searchable()
                            ->columnSpan(2),
                        Forms\Components\Select::make('unit')
                            ->disabled(function ($record) use ($ownerRequestUserId): bool {
                                $requestOwnerId = $record?->purchaseRequest?->user_id ?? $ownerRequestUserId;

                                return ($requestOwnerId != Auth::id() && Auth::user()->can('approve_purchase::requests'))
                                    || (Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'));
                            })
                            ->options(UnitsEnum::class)
                            ->native(false)
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\Select::make('budget_account_id')
                            ->label('Budget Account')
                            ->options(function (): array {
                                $this->getOwnerRecord()?->loadMissing('user');

                                $departmentId = $this->getOwnerRecord()?->user?->department_id
                                    ?? Auth::user()?->department_id;

                                return DepartmentBudgetOptions::filamentOptions($departmentId);
                            })
                            ->helperText(function (Forms\Get $get): string {
                                $this->getOwnerRecord()?->loadMissing('user');

                                $departmentId = $this->getOwnerRecord()?->user?->department_id
                                    ?? Auth::user()?->department_id;

                                return DepartmentBudgetOptions::helperText(
                                    $get('budget_account_id') ? (int) $get('budget_account_id') : null,
                                    $departmentId,
                                );
                            })
                            ->searchable()
                            ->disabled(function ($record) use ($ownerRequestUserId): bool {
                                $requestOwnerId = $record?->purchaseRequest?->user_id ?? $ownerRequestUserId;

                                return $requestOwnerId != Auth::id()
                                    && Auth::user()->is_hod == true
                                    && ! Auth::user()->can('send_approval_purchase::requests')
                                    && ! Auth::user()->can('approve_purchase::requests');
                            })
                            ->required()
                            ->columnSpan(4),
                        Forms\Components\TextInput::make('amount')
                            ->disabled(function ($record) use ($ownerRequestUserId): bool {
                                $requestOwnerId = $record?->purchaseRequest?->user_id ?? $ownerRequestUserId;

                                return ($requestOwnerId != Auth::id() && Auth::user()->can('approve_purchase::requests'))
                                    || (Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'));
                            })
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('est_cost')
                            ->disabled(function ($record) use ($ownerRequestUserId): bool {
                                $requestOwnerId = $record?->purchaseRequest?->user_id ?? $ownerRequestUserId;

                                return ($requestOwnerId != Auth::id() && Auth::user()->can('approve_purchase::requests'))
                                    || (Auth::user()->is_hod == true && ! Auth::user()->can('send_approval_purchase::requests'));
                            })
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
                                        $this->getOwnerRecord()?->loadMissing('user');
                                        $departmentId = $this->getOwnerRecord()?->user?->department_id
                                            ?? Auth::user()?->department_id;

                                        if (! $departmentId) {
                                            $fail('Requester has no department for budget allocation.');

                                            return;
                                        }

                                        if (PurchaseRequestBudget::allocatedForDepartment((int) $budgetAccountId, (int) $departmentId) <= 0) {
                                            $fail('This budget code is not allocated to the requester department.');

                                            return;
                                        }

                                        $available = PurchaseRequestBudget::availableForDepartment(
                                            (int) $budgetAccountId,
                                            (int) $departmentId,
                                        );

                                        if ($value > $available) {
                                            $fail(sprintf(
                                                "You don't have enough available funds for this budget code (available MVR %s).",
                                                number_format($available, 2),
                                            ));
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
