<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;

use App\Filament\Admin\Resources\PurchaseRequestsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
