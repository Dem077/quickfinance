<?php

namespace App\Filament\Admin\Resources\PettyCashReimbursmentResource\Pages;

use App\Filament\Admin\Resources\PettyCashReimbursmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPettyCashReimbursments extends ListRecords
{
    protected static string $resource = PettyCashReimbursmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
