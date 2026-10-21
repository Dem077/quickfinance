<?php

namespace App\Filament\Admin\Resources\PettyCashReimbursmentResource\RelationManagers;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrderDetails;
use App\Models\PurchaseOrders;
use App\Support\DepartmentBudgetOptions;
use App\Support\PettyCashClaimedPrItems;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PettyCashReimbursmentDetailRelationManager extends RelationManager
{
    protected static string $relationship = 'pettyCashReimbursmentDetails';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Radio::make('is_from_pr')
                    ->label('Is from PR?')
                    ->boolean()
                    ->default(true)
                    ->inline()
                    ->reactive()
                    ->live()
                    ->inlineLabel(false)
                    ->columnSpan(4),
                Forms\Components\DatePicker::make('date')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->disabled(fn ($record) => $record && $record->pettycashreimbursment->status->value !== 'draft')
                    ->required()
                    ->columnSpan(1),
                Forms\Components\Select::make('po_id')
                    ->label('Record ID')
                    ->options(function (): array {
                        $this->getOwnerRecord()?->loadMissing('user');
                        $departmentId = $this->getOwnerRecord()?->user?->department_id
                            ?? Auth::user()?->department_id;
                        $ownerId = $this->getOwnerRecord()?->id;
                        $claimedKeys = PettyCashClaimedPrItems::keys($ownerId ? (int) $ownerId : null);

                        return PurchaseOrders::query()
                            ->where('status', PurchaseOrderStatus::WaitingReimbursement->value)
                            ->where('payment_method', 'petty_cash')
                            ->when(
                                $departmentId,
                                fn ($query) => $query->whereHas(
                                    'purchaseRequest.user',
                                    fn ($userQuery) => $userQuery->where('department_id', $departmentId),
                                ),
                            )
                            ->with(['purchaseRequest', 'purchaseOrderDetails'])
                            ->get()
                            ->filter(fn ($po) => $po->purchaseOrderDetails->contains(
                                fn ($detail) => $detail->item_id
                                    && ! $claimedKeys->contains(PettyCashClaimedPrItems::key((int) $po->id, (int) $detail->item_id)),
                            ))
                            ->mapWithKeys(function ($po) {
                                $prNo = $po->purchaseRequest?->pr_no ?? 'N/A';

                                return [$po->id => "{$prNo} ({$po->po_no})"];
                            })
                            ->toArray();
                    })
                    ->native(false)
                    ->searchable()
                    ->reactive()
                    ->live()
                    ->hidden(fn (Get $get) => $get('is_from_pr') == false)
                    ->columnSpan(1)
                    ->nullable(),
                Forms\Components\Select::make('Vendor_id')
                    ->relationship('Vendor', 'name', fn ($query) => $query->orderBy('name'))
                    ->native(false)
                    ->preload()
                    ->searchable()
                    ->disabled(fn ($record) => $record && $record->pettycashreimbursment->status->value !== 'draft')
                    ->columnSpan(1)
                    ->required(),
                Forms\Components\TextInput::make('bill_no')
                    ->columnSpan(fn (Get $get) => $get('is_from_pr') == true ? 1 : 2)
                    ->disabled(fn ($record) => $record && $record->pettycashreimbursment->status->value !== 'draft')
                    ->required(),
                Forms\Components\Select::make('item_id')
                    ->label('Description')
                    ->native(false)
                    ->options(function (Get $get, $record) {
                        $poId = $get('po_id');
                        if (! $poId) {
                            return [];
                        }
                        $selectedItems = $record
                            ?->pettycashreimbursment
                            ->pettyCashReimbursmentDetails
                            ->reject(fn ($detail) => $detail->id === $record->id || is_null($detail->item_id))
                            ->pluck('item_id')
                            ->toArray() ?? [];

                        $ownerId = $record?->petty_cash_reimb_id ?? $this->getOwnerRecord()?->id;

                        $claimedItems = PettyCashClaimedPrItems::itemIdsForPo(
                            (int) $poId,
                            exceptReimbursmentId: $ownerId ? (int) $ownerId : null,
                            exceptDetailId: $record?->id,
                        );

                        return PurchaseOrderDetails::where('po_id', $poId)
                            ->whereNotIn('item_id', [...$selectedItems, ...$claimedItems])
                            ->with('items')
                            ->get()
                            ->filter(fn ($detail) => $detail->items !== null && $detail->items->name !== null)
                            ->mapWithKeys(fn ($detail) => [$detail->item_id => $detail->items->name])
                            ->toArray();
                    })
                    ->hidden(fn (Get $get) => $get('is_from_pr') == false)
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get, Set $set, $record) {
                        if ($state) {
                            $poId = $get('po_id');
                            if ($poId) {
                                $poDetail = PurchaseOrderDetails::where('po_id', $poId)
                                    ->where('item_id', $state)
                                    ->first();
                                if ($poDetail) {
                                    $set('amount', $poDetail->amount);
                                    if ($poDetail->budget_account_id) {
                                        $set('sub_budget_id', $poDetail->budget_account_id);
                                    }
                                }
                            }
                        }
                    })
                    ->required()
                    ->columnSpan(2)
                    ->nullable(),
                Forms\Components\TextInput::make('details')
                    ->columnSpan(2)
                    ->hidden(fn (Get $get) => $get('is_from_pr') == true)
                    ->disabled(fn ($record) => $record && $record->pettycashreimbursment->status->value !== 'draft')
                    ->required(),
                Forms\Components\Select::make('sub_budget_id')
                    ->label('Budget Account')
                    ->options(function (): array {
                        $this->getOwnerRecord()?->loadMissing('user');

                        $departmentId = $this->getOwnerRecord()?->user?->department_id
                            ?? Auth::user()?->department_id;

                        return DepartmentBudgetOptions::filamentOptions($departmentId);
                    })
                    ->searchable()
                    ->reactive()
                    ->helperText(function (Get $get): string {
                        $this->getOwnerRecord()?->loadMissing('user');
                        $departmentId = $this->getOwnerRecord()?->user?->department_id
                            ?? Auth::user()?->department_id;

                        return DepartmentBudgetOptions::helperText(
                            $get('sub_budget_id') ? (int) $get('sub_budget_id') : null,
                            $departmentId,
                        );
                    })
                    ->native(false)
                    ->required()
                    ->columnSpan(1)
                    ->nullable(),
                Forms\Components\TextInput::make('amount')
                    ->columnSpan(1)
                    ->disabled(fn (Get $get, $record) => $record && $record->pettycashreimbursment->status->value !== 'draft' || $get('is_from_pr') == true)
                    ->required()
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date('d-m-Y'),
                Tables\Columns\TextColumn::make('vendor.name'),
                Tables\Columns\TextColumn::make('details_item')
                    ->label('Details')
                    ->getStateUsing(fn ($record) => $record->items?->name ?? $record->details),
                Tables\Columns\TextColumn::make('bill_no'),
                Tables\Columns\TextColumn::make('subBudget.code'),
                Tables\Columns\TextColumn::make('purchaseOrder.po_no')
                    ->label('PO / PR No')
                    ->getStateUsing(function ($record) {
                        $po = $record->purchaseOrder;
                        $prNo = $po?->purchaseRequest?->pr_no ?? 'N/A';
                        $poNo = $po?->po_no ?? 'N/A';

                        return "{$poNo} ({$prNo})";
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->money('MVR'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (! empty($data['po_id']) && ! empty($data['item_id'])) {
                            PettyCashClaimedPrItems::assertAvailable((int) $data['po_id'], (int) $data['item_id']);
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record && $record->pettycashreimbursment->status->value === 'draft'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('check')
                        ->label('Check')
                        ->icon('heroicon-o-check-circle'),
                ]),
            ]);
    }
}
