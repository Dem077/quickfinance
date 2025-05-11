<?php

namespace App\Filament\Admin\Resources\AdvanceFormResource\Pages;

use App\Filament\Admin\Resources\AdvanceFormResource;
use Filament\Resources\Pages\ListRecords;

class ListAdvanceForms extends ListRecords
{
    protected static string $resource = AdvanceFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
