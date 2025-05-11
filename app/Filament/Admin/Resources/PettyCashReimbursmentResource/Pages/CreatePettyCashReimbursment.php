<?php

namespace App\Filament\Admin\Resources\PettyCashReimbursmentResource\Pages;

use App\Filament\Admin\Resources\PettyCashReimbursmentResource;
use App\Models\PettyCashReimbursmentDetail;
use Filament\Resources\Pages\CreateRecord;

class CreatePettyCashReimbursment extends CreateRecord
{
    protected static string $resource = PettyCashReimbursmentResource::class;

    protected function afterCreate(): void
    {
        $reimburesmentitems = $this->data['reimbursementsitems'] ?? [];

        foreach ($reimburesmentitems as $detail) {
            PettyCashReimbursmentDetail::create([
                'petty_cash_reimb_id' => $this->record->id,
                'Vendor_id' => $detail['Vendor_id'],
                'bill_no' => $detail['bill_no'],
                'date' => $detail['date'],
                'sub_budget_id' => $detail['sub_budget_id'],
                'details' => $detail['details'],
                'po_id' => $detail['po_id'],
                'amount' => $detail['amount'],
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
