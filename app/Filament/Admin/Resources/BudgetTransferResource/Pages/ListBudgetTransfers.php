<?php

namespace App\Filament\Admin\Resources\BudgetTransferResource\Pages;

use App\Filament\Admin\Resources\BudgetTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBudgetTransfers extends ListRecords
{
    protected static string $resource = BudgetTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
