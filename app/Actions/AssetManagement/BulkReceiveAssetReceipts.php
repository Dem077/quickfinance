<?php

namespace App\Actions\AssetManagement;

use App\Actions\Action;
use App\Enums\AssetReceiptStatus;
use App\Enums\ItemTypeEnum;
use App\Models\AssetReceipt;
use App\Services\AssetReceipt\AssetReceiptReceiver;
use App\Services\SnipeIt\SnipeItException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BulkReceiveAssetReceipts extends Action
{
    public function __construct(
        protected AssetReceiptReceiver $receiver,
    ) {}

    /**
     * @param  Collection<int, AssetReceipt>  $records
     * @param  array<string, mixed>  $data
     * @param  'assets'|'accessories'  $repeaterKey
     * @return array{succeeded: int, failures: array<int, string>}
     */
    public function handle(Collection $records, array $data, ItemTypeEnum $type, string $repeaterKey): array
    {
        $pending = $records->filter(function (AssetReceipt $record) use ($type): bool {
            $record->loadMissing('item');

            return $record->status === AssetReceiptStatus::Pending
                && $record->item?->type === $type;
        });

        if ($pending->isEmpty()) {
            return ['succeeded' => 0, 'failures' => ['No pending items to process.']];
        }

        $shared = collect($data)->except($repeaterKey)->all();
        $succeeded = 0;
        $failures = [];

        foreach ($data[$repeaterKey] ?? [] as $row) {
            $receiptId = (int) ($row['asset_receipt_id'] ?? 0);
            $receipt = $pending->firstWhere('id', $receiptId);

            if (! $receipt) {
                continue;
            }

            try {
                $this->receiver->receive(
                    $receipt,
                    $this->receiver->attributesFromFormData($shared, $row, $type)
                );
                $succeeded++;
            } catch (SnipeItException $exception) {
                $label = $row['unit_label'] ?? $row['line_label'] ?? 'Item #'.$receiptId;
                $failures[] = $label.': '.$exception->getMessage();
            }
        }

        return [
            'succeeded' => $succeeded,
            'failures' => array_map(fn (string $f) => Str::limit($f, 200), $failures),
        ];
    }
}
