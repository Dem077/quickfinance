<?php

namespace App\Filament\Admin\Resources\BudgetAccountsResource\Pages;

use App\Filament\Admin\Resources\BudgetAccountsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBudgetAccounts extends EditRecord
{
    protected static string $resource = BudgetAccountsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
