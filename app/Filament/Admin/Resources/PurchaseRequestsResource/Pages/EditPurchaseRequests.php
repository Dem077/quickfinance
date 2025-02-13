<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;

use App\Filament\Admin\Resources\PurchaseRequestsResource;
use App\Models\PurchaseRequests;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditPurchaseRequests extends EditRecord
{
    protected static string $resource = PurchaseRequestsResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
