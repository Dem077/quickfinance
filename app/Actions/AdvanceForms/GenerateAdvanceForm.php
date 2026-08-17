<?php

namespace App\Actions\AdvanceForms;

use App\Actions\Action;
use App\Enums\AdvanceFormStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\AdvanceForm;
use App\Models\PurchaseOrders;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateAdvanceForm extends Action
{
    /**
     * @param  array{qoation_no: string, expected_delivery: string|int, advance_amount: float|int}  $data
     */
    public function handle(PurchaseOrders $purchaseOrder, array $data, int $userId): AdvanceForm
    {
        if ($purchaseOrder->advance_form_id) {
            throw ValidationException::withMessages([
                'advance_form' => 'An advance form already exists for this purchase order.',
            ]);
        }

        if (
            ! $purchaseOrder->is_advance_form_required
            || $purchaseOrder->status !== PurchaseOrderStatus::Submitted
            || $purchaseOrder->payment_method !== 'purchase_order'
        ) {
            throw ValidationException::withMessages([
                'advance_form' => 'Advance form cannot be generated for this purchase order.',
            ]);
        }

        $total = (float) $purchaseOrder->purchaseOrderDetails()->sum('amount');
        $percentage = (float) $data['advance_amount'];
        $advanceAmount = ($percentage / 100) * $total;

        return DB::transaction(function () use ($purchaseOrder, $data, $userId, $percentage, $advanceAmount, $total): AdvanceForm {
            $advanceForm = AdvanceForm::create([
                'qoation_no' => $data['qoation_no'],
                'expected_delivery' => $data['expected_delivery'],
                'advance_percentage' => $percentage,
                'advance_amount' => $advanceAmount,
                'request_number' => $this->nextRequestNumber(),
                'vendors_id' => $purchaseOrder->vendor_id,
                'balance_amount' => $total - $advanceAmount,
                'generated_by' => $userId,
                'status' => AdvanceFormStatus::Draft,
            ]);

            $purchaseOrder->update([
                'advance_form_id' => $advanceForm->id,
            ]);

            return $advanceForm;
        });
    }

    private function nextRequestNumber(): string
    {
        $count = 1158;

        do {
            $requestNumber = sprintf('LADV/PROC/%04d', $count);
            $exists = AdvanceForm::query()->where('request_number', $requestNumber)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        return $requestNumber;
    }
}
