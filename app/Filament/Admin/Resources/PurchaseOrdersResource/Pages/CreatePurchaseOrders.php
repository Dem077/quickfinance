<?php

namespace App\Filament\Admin\Resources\PurchaseOrdersResource\Pages;

use App\Filament\Admin\Resources\PurchaseOrdersResource;
use App\Models\Item;
use App\Models\PurchaseOrderDetails;
use App\Models\PurchaseRequests;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrders extends CreateRecord
{
    protected static string $resource = PurchaseOrdersResource::class;

    protected function afterCreate(): void
    {
        $purchaseOrderDetails = $this->data['purchaseOrderDetails'] ?? [];

        foreach ($purchaseOrderDetails as $detail) {
            $item = Item::where('item_code', $detail['itemcode'])->first();
            $pr = PurchaseRequests::where('id', $this->data['pr_id'])->first();
            $pr->purchaseRequestDetails()->where('item_id', $item->id)->update(['is_utilized' => true]);
            PurchaseOrderDetails::create([
                'po_id' => $this->record->id,
                'item_id' => $item->id,
                'itemcode' => $detail['itemcode'],
                'desc' => $item->name,
                'budget_account_id' => $detail['budget_account'],
                'unit_measure' => $detail['unit_measure'],
                'qty' => $detail['qty'],
                'unit_price' => $detail['unit_price'],
                'tax_amount' => $detail['tax_amount'],
                'amount' => $detail['amount'],
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
