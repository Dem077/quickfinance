<?php

namespace App\Filament\Admin\Resources\PettyCashReimbursmentResource\Pages;

use App\Filament\Admin\Resources\PettyCashReimbursmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPettyCashReimbursment extends EditRecord
{
    protected static string $resource = PettyCashReimbursmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
