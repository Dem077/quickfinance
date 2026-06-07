<?php

namespace App\Filament\Admin\Resources\PurchaseOrdersResource\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrdersResource;
use App\Models\PettyCashReimbursment;
use App\Models\PurchaseRequests;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrders extends EditRecord
{
    protected static string $resource = PurchaseOrdersResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['payment_method'] ?? '') === 'petty_cash' && ! empty($data['po_no'])) {
            $selected = $data['po_no'];

            if ($selected === PettyCashReimbursment::GENERATE_FORM_NO_OPTION) {
                $data['po_no'] = PettyCashReimbursment::resolveFormNoForProcure($selected);
            }
        }

        return $data;
    }

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
                        $pr = PurchaseRequests::where('id', $prId)->first();
                        $pr->purchaseRequestDetails()
                            ->whereHas('items', function ($query) use ($item) {
                                $query->where('item_code', $item);
                            })
                            ->update(['is_utilized' => false]);
                    }
                }),
        ];
    }
}
