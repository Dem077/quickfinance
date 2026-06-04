<?php

namespace App\Services\SnipeIt;

use App\Enums\ItemTypeEnum;
use App\Models\AssetReceipt;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SnipeItService
{
    public function isEnabled(): bool
    {
        if (! config('snipe-it.enabled', true)) {
            return false;
        }

        return filled(config('snipe-it.url')) && filled(config('snipe-it.api_token'));
    }

    /**
     * @return array<int, string>
     */
    public function categoryOptions(): array
    {
        return $this->cachedOptions('categories.accessory', '/api/v1/categories', function (array $row): ?string {
            if (strcasecmp((string) data_get($row, 'category_type'), 'accessory') !== 0) {
                return null;
            }

            return data_get($row, 'name');
        }, ['category_type' => 'accessory']);
    }

    public function modelOptions(): array
    {
        return $this->cachedOptions('models', '/api/v1/models', function (array $row): ?string {
            $name = data_get($row, 'name');
            $number = data_get($row, 'model_number');

            if (! $name) {
                return null;
            }

            return $number ? "{$name} ({$number})" : $name;
        });
    }

    /**
     * @return array<int, string>
     */
    public function statusLabelOptions(): array
    {
        return $this->cachedOptions('statuslabels', '/api/v1/statuslabels', function (array $row): ?string {
            return data_get($row, 'name');
        });
    }

    /**
     * @return array<int, string>
     */
    public function locationOptions(): array
    {
        return $this->cachedOptions('locations', '/api/v1/locations', function (array $row): ?string {
            return data_get($row, 'name');
        });
    }

    /**
     * @return array<int, string>
     */
    public function supplierOptions(): array
    {
        return $this->cachedOptions('suppliers', '/api/v1/suppliers', function (array $row): ?string {
            return data_get($row, 'name');
        });
    }

    public function checkSerialNumber(string $serial, ?int $excludeHardwareId = null): SnipeItSerialCheck
    {
        $serial = trim($serial);

        if ($serial === '') {
            return new SnipeItSerialCheck(
                isAvailable: true,
                message: 'Enter a serial number, then click the check button.',
            );
        }

        if (! $this->isEnabled()) {
            return new SnipeItSerialCheck(
                isAvailable: true,
                message: 'Snipe-IT is not configured; serial check skipped.',
            );
        }

        $response = $this->client()->get(
            '/api/v1/hardware/byserial/'.rawurlencode($serial)
        );

        if (! $response->successful()) {
            throw new SnipeItException(
                'Could not check serial in Snipe-IT: '.$this->formatErrorMessage($response->json(), $response->body())
            );
        }

        $json = $response->json() ?? [];
        $messages = data_get($json, 'messages');

        if (data_get($json, 'status') === 'error' && is_string($messages) && stripos($messages, 'does not exist') !== false) {
            return new SnipeItSerialCheck(
                isAvailable: true,
                message: 'Serial is not used in Snipe-IT — OK to use.',
            );
        }

        $rows = data_get($json, 'rows', []);

        if ($rows === [] && filled(data_get($json, 'payload'))) {
            $rows = [data_get($json, 'payload')];
        }

        $conflicts = [];

        foreach ($rows as $row) {
            $id = (int) data_get($row, 'id');

            if (! $id || ($excludeHardwareId !== null && $id === $excludeHardwareId)) {
                continue;
            }

            $conflicts[] = [
                'id' => $id,
                'asset_tag' => data_get($row, 'asset_tag'),
                'name' => data_get($row, 'name'),
            ];
        }

        if ($conflicts === []) {
            return new SnipeItSerialCheck(
                isAvailable: true,
                message: 'Serial is not used in Snipe-IT — OK to use.',
            );
        }

        $summary = collect($conflicts)
            ->map(fn (array $asset): string => sprintf(
                '%s (tag: %s, ID: %d)',
                $asset['name'] ?: 'Unnamed asset',
                $asset['asset_tag'] ?: '—',
                $asset['id'],
            ))
            ->implode('; ');

        return new SnipeItSerialCheck(
            isAvailable: false,
            message: 'Serial already exists in Snipe-IT: '.$summary,
            conflicts: $conflicts,
        );
    }

    /**
     * @throws SnipeItException
     */
    public function createFromReceipt(AssetReceipt $receipt): SnipeItCreatedRecord
    {
        $receipt->loadMissing('item');

        return match ($receipt->item?->type) {
            ItemTypeEnum::Accessory => $this->createAccessoryFromReceipt($receipt),
            default => $this->createAssetFromReceipt($receipt),
        };
    }

    /**
     * @throws SnipeItException
     */
    public function createAssetFromReceipt(AssetReceipt $receipt): SnipeItCreatedRecord
    {
        if (! $this->isEnabled()) {
            throw new SnipeItException('Snipe-IT is not configured. Set SNIPE_IT_URL and SNIPE_IT_API_TOKEN in your .env file.');
        }

        $receipt->loadMissing(['purchaseOrder', 'item']);

        if (! $receipt->snipe_model_id) {
            throw new SnipeItException('Model is required (select a Snipe-IT model).');
        }

        if (! filled($receipt->cao_asset_code)) {
            throw new SnipeItException('CAO Asset Code is required.');
        }

        $payload = array_filter([
            'model_id' => (int) $receipt->snipe_model_id,
            'status_id' => (int) ($receipt->snipe_status_id ?: config('snipe-it.default_status_id')),
            'name' => $receipt->name ?: $receipt->asset_description,
            'serial' => $receipt->serial_number,
            'order_number' => $receipt->order_number ?: $receipt->invoice_number,
            'location_id' => $receipt->snipe_location_id,
            'supplier_id' => $receipt->snipe_supplier_id,
            'purchase_date' => $receipt->purchase_date?->format('Y-m-d'),
            'purchase_cost' => $receipt->purchase_cost,
            'notes' => $receipt->notes ?: $this->buildNotes($receipt),
            ...$this->customFieldPayloadFromReceipt($receipt),
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->client()->post('/api/v1/hardware', $payload);

        if (! $response->successful()) {
            throw new SnipeItException(
                'Snipe-IT could not create the asset: '.$this->formatErrorMessage($response->json(), $response->body())
            );
        }

        $json = $response->json() ?? [];

        if (data_get($json, 'status') !== 'success') {
            throw new SnipeItException(
                'Snipe-IT could not create the asset: '.$this->formatErrorMessage($json, $response->body())
            );
        }

        $hardwareId = $this->extractIdFromResponse($json) ?? $this->extractHardwareIdFromResponse($json);

        if (! $hardwareId) {
            throw new SnipeItException(
                'Snipe-IT reported success but did not return an asset ID. Try again or link the asset manually in Snipe-IT.'
            );
        }

        $assetTag = $this->extractAssetTagFromResponse($json)
            ?? $this->findHardwareAssetTagById($hardwareId);

        if (! $assetTag) {
            throw new SnipeItException(
                'Snipe-IT created the asset (ID: '.$hardwareId.') but did not return an asset tag.'
            );
        }

        return new SnipeItCreatedRecord('hardware', $hardwareId, $assetTag);
    }

    /**
     * @throws SnipeItException
     */
    public function createAccessoryFromReceipt(AssetReceipt $receipt): SnipeItCreatedRecord
    {
        if (! $this->isEnabled()) {
            throw new SnipeItException('Snipe-IT is not configured. Set SNIPE_IT_URL and SNIPE_IT_API_TOKEN in your .env file.');
        }

        $receipt->loadMissing(['purchaseOrder', 'item', 'purchaseOrderDetail']);

        if (! $receipt->snipe_category_id) {
            throw new SnipeItException('Category is required (select a Snipe-IT accessory category).');
        }

        $quantity = (int) ($receipt->snipe_quantity ?: $receipt->purchaseOrderDetail?->assetLineQuantity() ?: 1);

        if ($quantity < 1) {
            throw new SnipeItException('Quantity must be at least 1.');
        }

        $payload = array_filter([
            'name' => $receipt->name ?: $receipt->asset_description ?: $receipt->item?->name,
            'category_id' => (int) $receipt->snipe_category_id,
            'qty' => $quantity,
            'order_number' => $receipt->order_number ?: $receipt->invoice_number,
            'location_id' => $receipt->snipe_location_id,
            'supplier_id' => $receipt->snipe_supplier_id,
            'purchase_date' => $receipt->purchase_date?->format('Y-m-d'),
            'purchase_cost' => $receipt->purchase_cost,
            'model_number' => $receipt->model_number,
            'notes' => $receipt->notes ?: $this->buildNotes($receipt),
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->client()->post('/api/v1/accessories', $payload);

        if (! $response->successful()) {
            throw new SnipeItException(
                'Snipe-IT could not create the accessory: '.$this->formatErrorMessage($response->json(), $response->body())
            );
        }

        $json = $response->json() ?? [];

        if (data_get($json, 'status') !== 'success') {
            throw new SnipeItException(
                'Snipe-IT could not create the accessory: '.$this->formatErrorMessage($json, $response->body())
            );
        }

        $accessoryId = $this->extractIdFromResponse($json);

        if (! $accessoryId) {
            throw new SnipeItException('Snipe-IT reported success but did not return an accessory ID.');
        }

        $name = (string) (data_get($json, 'payload.name') ?: $payload['name'] ?? 'Accessory');

        return new SnipeItCreatedRecord('accessory', $accessoryId, name: $name);
    }

    protected function extractIdFromResponse(array $json): ?int
    {
        foreach ([
            'payload.id',
            'id',
        ] as $path) {
            $id = data_get($json, $path);

            if ($id !== null && $id !== '') {
                return (int) $id;
            }
        }

        return null;
    }

    protected function extractHardwareIdFromResponse(array $json): ?int
    {
        foreach ([
            'payload.id',
            'payload.asset.id',
            'id',
            'asset.id',
        ] as $path) {
            $id = data_get($json, $path);

            if ($id !== null && $id !== '') {
                return (int) $id;
            }
        }

        return null;
    }

    protected function extractAssetTagFromResponse(array $json): ?string
    {
        foreach ([
            'payload.asset_tag',
            'payload.asset.asset_tag',
            'asset_tag',
        ] as $path) {
            $tag = data_get($json, $path);

            if (filled($tag)) {
                return (string) $tag;
            }
        }

        return null;
    }

    protected function findHardwareAssetTagById(int $hardwareId): ?string
    {
        $response = $this->client()->get('/api/v1/hardware/'.$hardwareId);

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json() ?? [];

        if (data_get($json, 'status') === 'error') {
            return null;
        }

        return $this->extractAssetTagFromResponse($json);
    }

    protected function buildNotes(AssetReceipt $receipt): string
    {
        $poNo = $receipt->purchaseOrder?->po_no ?? 'N/A';

        return implode("\n", array_filter([
            'Created from Finance asset receipt #'.$receipt->id,
            'PO: '.$poNo,
            'Finance item: '.($receipt->item?->name ?? 'N/A'),
            $receipt->unitLabel(),
        ]));
    }

    /**
     * @return array<string, string>
     */
    protected function customFieldPayloadFromReceipt(AssetReceipt $receipt): array
    {
        $columns = config('snipe-it.custom_fields', []);
        $payload = [];

        $values = [
            'cao_asset_code' => $receipt->cao_asset_code,
            'finance_old_asset_tag' => $receipt->finance_old_asset_tag,
            'asset_class' => $receipt->asset_class,
            'po_reference' => $receipt->order_number ?: $receipt->invoice_number ?: $receipt->purchaseOrder?->po_no,
            'mac_address' => $receipt->mac_address,
        ];

        foreach ($values as $key => $value) {
            $column = $columns[$key] ?? null;

            if ($column && filled($value)) {
                $payload[$column] = (string) $value;
            }
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    /**
     * @param  array<string, mixed>  $query
     */
    protected function cachedOptions(string $cacheKey, string $path, callable $labelResolver, array $query = []): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        return Cache::remember(
            'snipe-it.'.$cacheKey,
            now()->addMinutes(5),
            fn (): array => $this->fetchOptions($path, $labelResolver, $query)
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, string>
     */
    protected function fetchOptions(string $path, callable $labelResolver, array $query = []): array
    {
        $options = [];
        $offset = 0;
        $limit = 100;

        do {
            $response = $this->client()->get($path, [
                'limit' => $limit,
                'offset' => $offset,
                ...$query,
            ]);

            if (! $response->successful()) {
                return $options;
            }

            $rows = data_get($response->json(), 'rows', []);

            foreach ($rows as $row) {
                $id = (int) data_get($row, 'id');
                $label = $labelResolver($row);

                if ($id && $label) {
                    $options[$id] = $label;
                }
            }

            $offset += $limit;
            $total = (int) data_get($response->json(), 'total', count($rows));
        } while ($offset < $total && count($rows) === $limit);

        asort($options);

        return $options;
    }

    protected function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('snipe-it.url'), '/');

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withToken((string) config('snipe-it.api_token'))
            ->timeout(30);
    }

    protected function formatErrorMessage(?array $json, ?string $body): string
    {
        $messages = data_get($json, 'messages');

        if (is_string($messages)) {
            return $messages;
        }

        if (is_array($messages)) {
            return collect($messages)->flatten()->filter()->implode(' ');
        }

        return Str::limit((string) ($body ?: 'Unknown error'), 500);
    }
}
