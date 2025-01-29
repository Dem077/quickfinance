<?php

namespace App\Filament\Admin\Resources\BudgetAccountsResource\Pages;

use App\Filament\Admin\Resources\BudgetAccountsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBudgetAccounts extends ListRecords
{
    protected static string $resource = BudgetAccountsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
