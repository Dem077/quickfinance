<?php

namespace App\Filament\Admin\Resources\PurchaseOrdersResource\Pages;

use App\Filament\Admin\Resources\PurchaseOrdersResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrders extends EditRecord
{
    protected static string $resource = PurchaseOrdersResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
