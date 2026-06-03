<?php

namespace App\Models;

use App\Enums\PettyCashStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

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
    ];

    protected $casts = [
        'status' => PettyCashStatus::class,
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

    public function VerifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function ApprovedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
