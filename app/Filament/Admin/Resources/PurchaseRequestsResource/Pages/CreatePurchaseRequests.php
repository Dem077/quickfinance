<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;

use App\Filament\Admin\Resources\PurchaseRequestsResource;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseRequests extends CreateRecord
{
    protected static string $resource = PurchaseRequestsResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['pr_no'] = PurchaseRequests::generateNextPrNo();

        return $data;
    }

    protected function afterCreate(): void
    {
        $purchaserequestDetails = $this->data['purchaseRequestDetails'] ?? [];

        foreach ($purchaserequestDetails as $detail) {
            PurchaseRequestDetails::create([
                'pr_id' => $this->record->id,
                'item_id' => $detail['item'],
                'unit' => $detail['unit'],
                'budget_account_id' => $detail['budget_account'],
                'amount' => $detail['amount'],
                'est_cost' => $detail['est_cost'],
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
