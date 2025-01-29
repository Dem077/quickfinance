<?php

namespace App\Filament\Admin\Resources\VendorsResource\Pages;

use App\Filament\Admin\Resources\VendorsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendors extends ListRecords
{
    protected static string $resource = VendorsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
