<?php

namespace App\Filament\Admin\Resources\DepartmentsResource\Pages;

use App\Filament\Admin\Resources\DepartmentsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDepartments extends EditRecord
{
    protected static string $resource = DepartmentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
