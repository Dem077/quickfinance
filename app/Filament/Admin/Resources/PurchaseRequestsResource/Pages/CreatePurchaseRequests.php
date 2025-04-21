<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;

use App\Enums\PurchaseRequestsStatus;
use App\Filament\Admin\Resources\PurchaseRequestsResource;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseRequests extends CreateRecord
{
    protected static string $resource = PurchaseRequestsResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $year = date('Y');
        $count = PurchaseRequests::whereYear('created_at', $year)->count() + 1;

        do {
            $pr_no = sprintf('PR/AGRO/%s/%04d', $year, $count);
            $exists = PurchaseRequests::where('pr_no', $pr_no)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        $data['pr_no'] = $pr_no;

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
                'status' => PurchaseRequestsStatus::Draft->value,
                'est_cost' => $detail['est_cost'],
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
