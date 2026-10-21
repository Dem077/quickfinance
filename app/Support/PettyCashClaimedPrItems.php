<?php

namespace App\Support;

use App\Models\PettyCashReimbursmentDetail;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PettyCashClaimedPrItems
{
    /**
     * @return Collection<int, string>
     */
    public static function keys(?int $exceptReimbursmentId = null, ?int $exceptDetailId = null): Collection
    {
        return self::query($exceptReimbursmentId, $exceptDetailId)
            ->get(['po_id', 'item_id'])
            ->map(fn (PettyCashReimbursmentDetail $row): string => self::key((int) $row->po_id, (int) $row->item_id))
            ->unique()
            ->values();
    }

    /**
     * @return list<int>
     */
    public static function itemIdsForPo(int $poId, ?int $exceptReimbursmentId = null, ?int $exceptDetailId = null): array
    {
        return self::query($exceptReimbursmentId, $exceptDetailId)
            ->where('po_id', $poId)
            ->pluck('item_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function key(int $poId, int $itemId): string
    {
        return $poId.':'.$itemId;
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    public static function assertDetailsAvailable(
        array $details,
        ?int $exceptReimbursmentId = null,
        string $keyPrefix = 'details',
    ): void {
        $seen = [];

        foreach (array_values($details) as $index => $row) {
            $poId = (int) ($row['po_id'] ?? 0);
            $itemId = (int) ($row['item_id'] ?? 0);

            if ($poId === 0 || $itemId === 0) {
                continue;
            }

            $key = self::key($poId, $itemId);

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "{$keyPrefix}.{$index}.item_id" => 'This purchase request item is already on this reimbursement.',
                ]);
            }

            $seen[$key] = true;

            if (self::isClaimed($poId, $itemId, $exceptReimbursmentId)) {
                throw ValidationException::withMessages([
                    "{$keyPrefix}.{$index}.item_id" => 'This purchase request item is already on another petty cash reimbursement.',
                ]);
            }
        }
    }

    public static function assertAvailable(
        int $poId,
        int $itemId,
        ?int $exceptReimbursmentId = null,
        ?int $exceptDetailId = null,
        string $errorKey = 'item_id',
    ): void {
        if (self::isClaimed($poId, $itemId, $exceptReimbursmentId, $exceptDetailId)) {
            throw ValidationException::withMessages([
                $errorKey => 'This purchase request item is already on another petty cash reimbursement.',
            ]);
        }
    }

    public static function isClaimed(
        int $poId,
        int $itemId,
        ?int $exceptReimbursmentId = null,
        ?int $exceptDetailId = null,
    ): bool {
        return self::query($exceptReimbursmentId, $exceptDetailId)
            ->where('po_id', $poId)
            ->where('item_id', $itemId)
            ->exists();
    }

    private static function query(?int $exceptReimbursmentId = null, ?int $exceptDetailId = null)
    {
        return PettyCashReimbursmentDetail::query()
            ->whereNotNull('po_id')
            ->whereNotNull('item_id')
            ->when($exceptReimbursmentId, fn ($query) => $query->where('petty_cash_reimb_id', '!=', $exceptReimbursmentId))
            ->when($exceptDetailId, fn ($query) => $query->whereKeyNot($exceptDetailId));
    }
}
