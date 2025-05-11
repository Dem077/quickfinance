<?php

namespace App\Filament\Admin\Resources\PurchaseOrdersResource\Pages;

use App\Filament\Admin\Resources\PurchaseOrdersResource;
use App\Models\PurchaseOrderDetails;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrders extends CreateRecord
{
    protected static string $resource = PurchaseOrdersResource::class;

    protected function afterCreate(): void
    {
        $purchaseOrderDetails = $this->data['purchaseOrderDetails'] ?? [];

        foreach ($purchaseOrderDetails as $detail) {
            PurchaseOrderDetails::create([
                'po_id' => $this->record->id,
                'itemcode' => $detail['itemcode'],
                'desc' => $detail['desc'],
                'budget_account_id' => $detail['budget_account'],
                'unit_measure' => $detail['unit_measure'],
                'qty' => $detail['qty'],
                'unit_price' => $detail['unit_price'],
                'amount' => $detail['amount'],
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
