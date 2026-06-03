<?php

namespace App\Models;

use App\Enums\PettyCashStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PettyCashReimbursment extends Model
{
    public const GENERATE_FORM_NO_OPTION = '__generate_new__';

    protected $fillable = [
        'date',
        'user_id',
        'status',
        'form_no',
        'supporting_documents',
        'pv_number',
        'verified_by',
        'approved_by',
        'budget_deducted_at',
    ];

    protected $casts = [
        'status' => PettyCashStatus::class,
        'budget_deducted_at' => 'datetime',
    ];

    public static function generateNextFormNo(): string
    {
        $year = date('Y');
        $prefix = "PTC/AGR/{$year}/";

        $maxSequence = self::query()
            ->where('form_no', 'like', $prefix.'%')
            ->pluck('form_no')
            ->map(function (?string $formNo) use ($prefix): int {
                if (! $formNo || ! str_starts_with($formNo, $prefix)) {
                    return 0;
                }

                $sequence = substr($formNo, strlen($prefix));

                return is_numeric($sequence) ? (int) $sequence : 0;
            })
            ->max() ?? 0;

        $count = $maxSequence + 1;

        do {
            $formNo = sprintf('PTC/AGR/%s/%04d', $year, $count);
            $exists = self::where('form_no', $formNo)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        return $formNo;
    }

    public static function draftFormNoOptions(?string $includeFormNo = null, ?int $excludePurchaseOrderId = null): array
    {
        $usedFormNos = PurchaseOrders::query()
            ->where('payment_method', 'petty_cash')
            ->when($excludePurchaseOrderId, fn ($query) => $query->where('id', '!=', $excludePurchaseOrderId))
            ->pluck('po_no');

        $drafts = self::query()
            ->with('user.department')
            ->where('status', PettyCashStatus::Draft)
            ->whereNotNull('form_no')
            ->where('form_no', '!=', '')
            ->whereNotIn('form_no', $usedFormNos)
            ->orderBy('form_no')
            ->get()
            ->mapWithKeys(fn (self $reimbursment) => [
                $reimbursment->form_no => self::formatFormNoOptionLabel(
                    $reimbursment->form_no,
                    $reimbursment->user?->department?->name,
                ),
            ])
            ->all();

        if ($includeFormNo && ! isset($drafts[$includeFormNo])) {
            $reimbursment = self::query()
                ->with('user.department')
                ->where('form_no', $includeFormNo)
                ->first();

            $drafts[$includeFormNo] = self::formatFormNoOptionLabel(
                $includeFormNo,
                $reimbursment?->user?->department?->name,
            );
        }

        $next = self::generateNextFormNo();
        $drafts[self::GENERATE_FORM_NO_OPTION] = "Generate new ({$next})";

        return $drafts;
    }

    protected static function formatFormNoOptionLabel(string $label, ?string $departmentName): string
    {
        if ($departmentName) {
            return "{$label} — {$departmentName}";
        }

        return $label;
    }

    public static function createDraftWithFormNo(?string $formNo = null): self
    {
        return self::create([
            'form_no' => $formNo ?? self::generateNextFormNo(),
            'date' => now()->toDateString(),
            'user_id' => Auth::id(),
            'status' => PettyCashStatus::Draft,
        ]);
    }

    public static function resolveFormNoForProcure(string $selected): string
    {
        if ($selected === self::GENERATE_FORM_NO_OPTION) {
            return self::createDraftWithFormNo()->form_no;
        }

        return $selected;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pettyCashReimbursmentDetails(): HasMany
    {
        return $this->hasMany(PettyCashReimbursmentDetail::class, 'petty_cash_reimb_id');
    }

    public function hasBudgetBeenDeducted(): bool
    {
        return $this->budget_deducted_at !== null;
    }

    public function deductFromDepartmentBudgets(?int $by = null): void
    {
        if ($this->hasBudgetBeenDeducted()) {
            return;
        }

        $by ??= Auth::id();
        $this->loadMissing('pettyCashReimbursmentDetails', 'user');

        $departmentId = $this->user?->department_id;
        if (! $departmentId) {
            return;
        }

        $deducted = false;

        DB::transaction(function () use ($by, $departmentId, &$deducted) {
            foreach ($this->pettyCashReimbursmentDetails as $detail) {
                if (! $detail->sub_budget_id) {
                    continue;
                }

                $allocation = SubBudgetDepartmentAllocation::query()
                    ->where('sub_budget_account_id', $detail->sub_budget_id)
                    ->where('department_id', $departmentId)
                    ->first();

                if (! $allocation) {
                    continue;
                }

                $allocation->update([
                    'amount' => $allocation->amount - $detail->amount,
                ]);

                BudgetTransactionHistory::createtransaction(
                    $detail->sub_budget_id,
                    'Petty Cash Reimbursement',
                    $detail->amount,
                    (float) $allocation->fresh()->amount,
                    'Petty Cash finance approved — '.($this->form_no ?? '#'.$this->id),
                    $by,
                );

                $deducted = true;
            }

            if ($deducted) {
                $this->update(['budget_deducted_at' => now()]);
            }
        });
    }

    public function restoreDepartmentBudgets(?int $by = null): void
    {
        if (! $this->hasBudgetBeenDeducted()) {
            return;
        }

        $by ??= Auth::id();
        $this->loadMissing('pettyCashReimbursmentDetails', 'user');

        $departmentId = $this->user?->department_id;
        if (! $departmentId) {
            return;
        }

        DB::transaction(function () use ($by, $departmentId) {
            foreach ($this->pettyCashReimbursmentDetails as $detail) {
                if (! $detail->sub_budget_id) {
                    continue;
                }

                $allocation = SubBudgetDepartmentAllocation::query()
                    ->where('sub_budget_account_id', $detail->sub_budget_id)
                    ->where('department_id', $departmentId)
                    ->first();

                if (! $allocation) {
                    continue;
                }

                $allocation->update([
                    'amount' => $allocation->amount + $detail->amount,
                ]);

                BudgetTransactionHistory::createtransaction(
                    $detail->sub_budget_id,
                    'Petty Cash Reimbursement Reversal',
                    $detail->amount,
                    (float) $allocation->fresh()->amount,
                    'Petty Cash finance approval reversed — '.($this->form_no ?? '#'.$this->id),
                    $by,
                );
            }

            $this->update(['budget_deducted_at' => null]);
        });
    }

    public function VerifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function ApprovedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
