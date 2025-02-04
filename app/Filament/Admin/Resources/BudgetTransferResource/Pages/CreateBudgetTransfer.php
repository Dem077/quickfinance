<?php

namespace App\Filament\Admin\Resources\BudgetTransferResource\Pages;

use App\Filament\Admin\Resources\BudgetTransferResource;
use App\Models\SubBudgetAccounts;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBudgetTransfer extends CreateRecord
{
    protected static string $resource = BudgetTransferResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // Deduct from source budget
        $fromBudget = SubBudgetAccounts::find($record->from_budget_id);
        $fromBudget->update([
            'amount' => $fromBudget->amount - $record->amount
        ]);

        // Add to destination budget
        $toBudget = SubBudgetAccounts::find($record->to_budget_id);
        $toBudget->update([
            'amount' => $toBudget->amount + $record->amount
        ]);

        Notification::make()
            ->title('Budget transferred successfully')
            ->success()
            ->send();
    }
}
