<?php

namespace App\Filament\Admin\Resources\PettyCashReimbursmentResource\RelationManagers;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrders;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PettyCashReimbursmentDetailRelationManager extends RelationManager
{
    protected static string $relationship = 'pettyCashReimbursmentDetails';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->disabled(fn ($record) => $record && $record->pettycashreimbursment->status->value !== 'draft')
                    ->required()
                    ->columnSpan(1),
                Forms\Components\Select::make('Vendor_id')
                    ->relationship('Vendor', 'name' , fn ($query) => $query->orderBy('name') )
                    ->native(false)
                    ->preload()
                    ->searchable()
                    ->disabled(fn ($record) => $record && $record->pettycashreimbursment->status->value !== 'draft')
                    ->columnSpan(2)
                    ->required(),
                Forms\Components\TextInput::make('bill_no')
                    ->columnSpan(2)
                    ->disabled(fn ($record) => $record && $record->pettycashreimbursment->status->value !== 'draft')
                    ->required(),
                Forms\Components\TextInput::make('details')
                    ->columnSpan(2)
                    ->disabled(fn ($record) => $record && $record->pettycashreimbursment->status->value !== 'draft')
                    ->required(),
                Forms\Components\Select::make('sub_budget_id')
                    ->relationship('SubBudget', 'code')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record) => $record->department_id
                        ? "{$record->name} - {$record->department->name} ({$record->code})"
                        : "{$record->name} ({$record->code})"
                    )
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(2)
                    ->nullable(),
                Forms\Components\Select::make('po_id')
                    ->label('Record ID')
                    ->options(
                        \App\Models\PurchaseOrders::where('status', \App\Enums\PurchaseOrderStatus::WaitingReimbursement->value)
                            ->where('payment_method', 'petty_cash')
                            ->with('purchaseRequest')
                            ->get()
                            ->mapWithKeys(function ($po) {
                                $prNo = $po->purchaseRequest?->pr_no ?? 'N/A';
                                return [
                                    $po->id => "{$prNo} ({$po->po_no})"
                                ];
                            })
                            ->toArray()
                    )
                    ->native(false)
                    ->helperText('Attach Records from Procure fuction for reference')
                    ->disabled(fn ($record) => $record && $record->pettycashreimbursment->status->value !== 'draft')
                    ->searchable()
                    ->nullable(),

                Forms\Components\TextInput::make('amount')
                    ->columnSpan(1)
                    ->disabled(fn ($record) => $record && $record->pettycashreimbursment->status->value !== 'draft')
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
                Tables\Columns\TextColumn::make('details'),
                Tables\Columns\TextColumn::make('bill_no'),
                Tables\Columns\TextColumn::make('details'),
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
                    ->money('MVR', locale: 'us'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    // ->visible(fn ($record) => ! Auth::user()->can('fin_hod_approve_petty::cash::reimbursment')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record && $record->pettycashreimbursment->status->value === 'draft'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }
}
