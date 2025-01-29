<?php

namespace App\Filament\Admin\Resources\VendorsResource\Pages;

use App\Filament\Admin\Resources\VendorsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVendors extends EditRecord
{
    protected static string $resource = VendorsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
