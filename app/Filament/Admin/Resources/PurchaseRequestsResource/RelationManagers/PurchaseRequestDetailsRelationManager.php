<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\RelationManagers;

use App\Enums\UnitsEnum;
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
                            ->options(function () {
                                $departmentId = Auth::user()?->department_id;

                                return \App\Models\SubBudgetAccounts::with(['allocations' => function ($query) use ($departmentId) {
                                    if ($departmentId) {
                                        $query->where('department_id', $departmentId);
                                    }
                                }, 'allocations.department', 'allocations.location'])
                                    ->get()
                                    ->filter(function ($row) use ($departmentId) {
                                        return $departmentId
                                            ? $row->allocations->firstWhere('department_id', $departmentId)
                                            : $row->allocations->isNotEmpty();
                                    })
                                    ->mapWithKeys(function ($row) {
                                        $allocation = $row->allocations->first();
                                        $deptName = $allocation?->department?->name;
                                        $amount = $allocation?->amount ?? 0;
                                        $location = $allocation?->location?->name ? ' / '.$allocation->location->name : '';

                                        $label = $row->code.' - '.$row->name;
                                        if ($deptName) {
                                            $label .= ' ('.$deptName.': '.number_format($amount, 2).$location.')';
                                        } elseif ($location) {
                                            $label .= ' ('.$location.')';
                                        }

                                        return [$row->id => $label];
                                    })
                                    ->toArray();
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
                                        $departmentId = Auth::user()?->department_id;

                                        $account = SubBudgetAccounts::with([
                                            'allocations' => fn ($query) => $query->where('department_id', $departmentId),
                                        ])->find($budgetAccountId);

                                        $departmentAllocation = $account?->allocations->first();

                                        if (! $departmentAllocation) {
                                            $fail('This budget code is not allocated to your department.');
                                            return;
                                        }

                                        if ($value > $departmentAllocation->amount) {
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
