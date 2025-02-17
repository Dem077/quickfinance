<?php

namespace App\Filament\Admin\Resources\PurchaseOrdersResource\Pages;

use App\Filament\Admin\Resources\PurchaseOrdersResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrdersResource::class;

    
    protected ?string $heading = 'Procure';

    protected static ?string $navigationLabel = 'Procure';

    protected static ?string $slug = 'procure';

    protected static ?string $title = 'Procure';
    

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
