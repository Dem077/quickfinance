<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        // if ($this->record->is_hod == true) {
        //     $this->record->update([
        //         'hod_of' => $this->record->department_id,
        //     ]);
        // }
        // else {
        //     $this->record->update([
        //         'hod_of' => null,
        //     ]);
        // }
    }
}
