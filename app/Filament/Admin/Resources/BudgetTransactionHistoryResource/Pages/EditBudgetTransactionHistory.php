<?php

namespace App\Filament\Admin\Resources\BudgetTransactionHistoryResource\Pages;

use App\Filament\Admin\Resources\BudgetTransactionHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBudgetTransactionHistory extends EditRecord
{
    protected static string $resource = BudgetTransactionHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
