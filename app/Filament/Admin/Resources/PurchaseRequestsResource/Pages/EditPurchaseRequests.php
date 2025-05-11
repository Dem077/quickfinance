<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;

use App\Filament\Admin\Resources\PurchaseRequestsResource;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseRequests extends EditRecord
{
    protected static string $resource = PurchaseRequestsResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
