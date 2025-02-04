<?php

namespace App\Filament\Admin\Resources\BudgetTransferResource\Pages;

use App\Filament\Admin\Resources\BudgetTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBudgetTransfer extends EditRecord
{
    protected static string $resource = BudgetTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
