<?php

namespace App\Filament\Admin\Resources\AdvanceFormResource\Pages;

use App\Filament\Admin\Resources\AdvanceFormResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdvanceForm extends EditRecord
{
    protected static string $resource = AdvanceFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
