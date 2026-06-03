<?php

namespace App\Filament\Admin\Resources\PurchaseOrdersResource\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrdersResource;
use App\Enums\AdvanceFormStatus;
use App\Models\AdvanceForm;
use App\Models\BudgetTransactionHistory;
use App\Models\Item;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewPurchaseOrders extends ViewRecord
{
    protected static string $resource = PurchaseOrdersResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn ($record) => $record->status == PurchaseOrderStatus::Draft)
                ->before(function (Actions\DeleteAction $action) {

                    $poId = $this->record->id;
                    $prId = $this->record->pr_id;
                    $purchaseOrderDetails = $this->record->purchaseOrderDetails;

                    foreach ($purchaseOrderDetails as $detail) {
                        $item = $detail->itemcode;
                        $pr = \App\Models\PurchaseRequests::where('id', $prId)->first();
                        $pr->purchaseRequestDetails()
                            ->whereHas('items', function ($query) use ($item) {
                                $query->where('item_code', $item);
                            })
                            ->update(['is_utilized' => false]);
                    }
                }),
            Actions\EditAction::make()
                ->button()
                ->visible(fn ($record) => (($record->status == PurchaseOrderStatus::Draft && Auth::user()->can('create_purchase::orders')) || (
                    Auth::user()->can('approve_purchase::requests')))),

            Actions\Action::make('view_advance_form')
                ->label('View Advance Form')
                ->icon('heroicon-o-eye')
                ->button()
                ->visible(fn ($record): bool => ! empty($record->advance_form_id))
                ->url(fn ($record): string => route('purchase-orders.advance-form.download', $record))
                ->openUrlInNewTab(),
            Actions\Action::make('purchase_order_submit')
                ->label('Submit')
                ->button()
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn ($record) => $record->status == PurchaseOrderStatus::Draft &&
                    Auth::user()->can('create_purchase::orders')
                )
                ->action(function (PurchaseOrders $record) {
                    $record->update([
                        'status' => PurchaseOrderStatus::Submitted,
                        'is_submitted' => true,
                    ]);

                    $record->syncAssetReceipts();

                    foreach ($record->purchaseOrderDetails as $detail) {

                        $itemid = Item::where('item_code', $detail->itemcode)->first()->id;

                        PurchaseRequestDetails::where('item_id', $itemid)->where('pr_id', $record->pr_id)->update([
                            'is_utilized' => true,
                        ]);
                        PurchaseRequests::checkAndUpdateClosedStatus($record->pr_id);
                    }
                    Notification::make()
                        ->title('PO Submitted successfully')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('regenerate_advance_form')
                ->label('Regenerate Advance Form')
                ->icon('heroicon-o-document')
                ->color('warning')
                ->button()
                ->requiresConfirmation()
                ->modalHeading('Advance Form Details')
                ->modalSubheading('Please fill in the required fields')
                ->modalButton('Generate')
                ->visible(fn ($record) => $record->advance_form_id
                    && $record->is_advance_form_required
                    && $record->status == PurchaseOrderStatus::Submitted
                    && $record->payment_method == 'purchase_order'
                    && Auth::user()->can('create_purchase::orders')
                    && $record->advanceForm?->status === AdvanceFormStatus::Draft
                )
                ->form([
                    TextInput::make('qoation_no')
                        ->label('Qoaution No')
                        ->required(),
                    TextInput::make('expected_delivery')
                        ->label('Expected Delivery In Days')
                        ->required(),
                    TextInput::make('advance_amount')
                        ->label('Advance Amount %')
                        ->numeric()
                        ->suffix('%')
                        ->required(),
                ])
                ->action(function (array $data, PurchaseOrders $record) {

                    // Create the Advance Form record with user inputs
                    $record->advanceForm()->update([
                        'qoation_no' => $data['qoation_no'],
                        'expected_delivery' => $data['expected_delivery'],
                        'advance_percentage' => ($data['advance_amount']),
                        'advance_amount' => (($data['advance_amount'] / 100) * $record->purchaseOrderDetails()->sum('amount')),
                        'balance_amount' => $record->purchaseOrderDetails()->sum('amount') - ($data['advance_amount'] / 100) * $record->purchaseOrderDetails()->sum('amount'),
                        'generated_by' => Auth::id(),
                    ]);

                    // Redirect to the route that generates the PDF with the advance form data
                    return redirect()->route('purchase-orders.advance-form.download', $record);

                }),
            Actions\Action::make('generate_advance_form')
                ->label('Generate Advance Form')
                ->icon('heroicon-o-document')
                ->button()
                ->color('info')
                ->modalHeading('Advance Form Details')
                ->visible(fn ($record) => ! $record->advance_form_id && $record->is_advance_form_required && $record->status == PurchaseOrderStatus::Submitted && $record->payment_method == 'purchase_order' && Auth::user()->can('create_purchase::orders')
                )
                ->form([
                    TextInput::make('qoation_no')
                        ->label('Qoaution No')
                        ->required(),
                    TextInput::make('expected_delivery')
                        ->label('Expected Delivery In Days')
                        ->numeric()
                        ->required(),
                    TextInput::make('advance_amount')
                        ->label('Advance Amount %')
                        ->numeric()
                        ->suffix('%')
                        ->required(),
                ])
                ->action(function (array $data, PurchaseOrders $record) {

                    $count = 1157 + 1;

                    do {
                        $request_number = sprintf('LADV/PROC/%04d', $count);
                        $exists = AdvanceForm::where('request_number', $request_number)->exists();
                        if ($exists) {
                            $count++;
                        }
                    } while ($exists);

                    // Create the Advance Form record with user inputs
                    $advanceForm = $record->advanceForm()->create([
                        'qoation_no' => $data['qoation_no'],
                        'expected_delivery' => $data['expected_delivery'],
                        'advance_percentage' => ($data['advance_amount']),
                        'advance_amount' => (($data['advance_amount'] / 100) * $record->purchaseOrderDetails()->sum('amount')),
                        'request_number' => $request_number,
                        'vendors_id' => $record->vendor_id,
                        'balance_amount' => $record->purchaseOrderDetails()->sum('amount') - ($data['advance_amount'] / 100) * $record->purchaseOrderDetails()->sum('amount'),
                        'generated_by' => Auth::id(),
                        'status' => AdvanceFormStatus::Submitted,
                    ]);
                    $record->update([
                        'advance_form_id' => $advanceForm->id,
                    ]);

                    // Redirect to the route that generates the PDF with the advance form data
                    return redirect()->route('purchase-orders.advance-form.download', $record);

                }),

            Actions\Action::make('upload_supporting_document')
                ->label('Upload Reciept')
                ->icon('heroicon-o-document')
                ->button()
                ->visible(fn ($record) => $record->payment_method == 'petty_cash' && ! $record->supporting_document && Auth::user()->can('create_purchase::orders') && $record->status !== PurchaseOrderStatus::Closed)
                ->form([
                    FileUpload::make('supporting_document')
                        ->label('Document')
                        ->required(),
                ])
                ->action(function (PurchaseOrders $record, array $data) {
                    $record->update([
                        'supporting_document' => $data['supporting_document'],
                    ]);
                    Notification::make()
                        ->title('Document uploaded successfully')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('view_supporting_document')
                ->label('View Reciept')
                ->button()
                ->icon('heroicon-o-eye')
                ->visible(fn ($record) => $record->supporting_document)
                ->url(fn ($record) => asset('storage/'.$record->supporting_document))
                ->openUrlInNewTab(),

            Actions\Action::make('purchase_order_close')
                ->label('Close')
                ->icon('heroicon-o-check-circle')
                ->color('danger')
                ->button()
                ->modalHeading(fn (PurchaseOrders $record) => $record->payment_method === 'purchase_order'
                    ? 'Close Purchase Order'
                    : 'Close Procure Record')
                ->modalDescription(fn (PurchaseOrders $record) => $record->payment_method === 'purchase_order'
                    ? 'Enter the GRN number to close this purchase order. This action cannot be undone.'
                    : 'Are you sure you want to close this record? This action cannot be undone.')
                ->form(fn (PurchaseOrders $record): array => $record->payment_method === 'purchase_order'
                    ? [
                        TextInput::make('grn_number')
                            ->label('GRN Number')
                            ->required()
                            ->maxLength(255),
                    ]
                    : [])
                ->requiresConfirmation(fn (PurchaseOrders $record) => $record->payment_method !== 'purchase_order')
                ->visible(fn ($record) => $record->status == PurchaseOrderStatus::Submitted
                    && Auth::user()->can('close_purchase::orders')
                    && (
                        $record->payment_method === 'purchase_order'
                        || ($record->payment_method === 'petty_cash' && $record->supporting_document)
                    )
                )
                ->action(function (PurchaseOrders $record, array $data) {
                    if ($record->payment_method == 'petty_cash') {
                        $record->update([
                            'status' => PurchaseOrderStatus::WaitingReimbursement,
                            'is_closed_by' => Auth::id(),
                        ]);
                    } else {
                        $record->syncAssetReceipts();

                        $record->update([
                            'status' => PurchaseOrderStatus::Closed,
                            'is_closed_by' => Auth::id(),
                            'grn_number' => $data['grn_number'],
                        ]);
                    }

                    if ($record->payment_method == 'purchase_order') {

                        foreach ($record->purchaseOrderDetails as $detail) {
                            $detail->budgetAccount->update([
                                'amount' => $detail->budgetAccount->amount - $detail->amount, ]);
                            BudgetTransactionHistory::createtransaction($detail->budgetAccount->id, 'Purchase Order', $detail->amount, $detail->budgetAccount->amount, 'Purchase Order Closed for PO ('.$record->po_no.' | Item: '.$detail->desc.' )', Auth::id());
                        }
                        // $record->purchaseRequest->budgetAccount->update([
                        //     'amount' => $record->purchaseRequest->budgetAccount->amount - $record->purchaseOrderDetails()->sum('amount'),
                        // ]);

                        // BudgetTransactionHistory::createtransaction($record->purchaseRequest->budgetAccount->id, 'Purchase Order', $record->purchaseOrderDetails()->sum('amount'), $record->purchaseRequest->budgetAccount->amount, 'Purchase Order Closed', Auth::id());

                    }

                    PurchaseRequests::checkAndUpdateClosedStatus($record->pr_id);

                    Notification::make()
                        ->title('PO Closed successfully')
                        ->success()
                        ->send();
                }),
        ];
    }
}
