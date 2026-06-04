<?php

namespace App\Filament\Admin\Resources\AssetManagementResource\Pages;

use App\Filament\Admin\Resources\AssetManagementResource;
use App\Models\PurchaseOrders;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class ListAssetManagement extends ListRecords
{
    protected static string $resource = AssetManagementResource::class;

    protected function paginateTableQuery(Builder $query): Paginator
    {
        $paginator = parent::paginateTableQuery($query);

        $paginator->getCollection()->each(function (PurchaseOrders $purchaseOrder): void {
            $purchaseOrder->syncAssetReceipts();
        });

        return $paginator;
    }
}
