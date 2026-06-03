<?php

namespace App\Filament\Forms;

use App\Models\AssetReceipt;
use App\Services\SnipeIt\SnipeItException;
use App\Services\SnipeIt\SnipeItService;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Notifications\Notification;

class SnipeItHardwareForm
{
    public static function schema(?AssetReceipt $receipt = null): array
    {
        $snipeIt = app(SnipeItService::class);

        if (! $snipeIt->isEnabled()) {
            return [
                Forms\Components\Placeholder::make('snipe_disabled')
                    ->label('Snipe-IT')
                    ->content('Snipe-IT is not configured. Add SNIPE_IT_URL and SNIPE_IT_API_TOKEN to your .env file.'),
            ];
        }

        $receipt?->loadMissing(['purchaseOrder', 'purchaseOrderDetail', 'item']);

        return [
            Forms\Components\Section::make('Snipe-IT Asset')
                ->description('These fields match your Snipe-IT asset form and are sent when you mark the item as received.')
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('asset_tag_info')
                        ->label('Asset Tag')
                        ->content('Assigned automatically by Snipe-IT when you mark this item as received, then saved here.')
                        ->columnSpanFull(),
                    Forms\Components\Select::make('snipe_status_id')
                        ->label('Status')
                        ->options(fn () => $snipeIt->statusLabelOptions())
                        ->default(config('snipe-it.default_status_id'))
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('snipe_model_id')
                        ->label('Model')
                        ->options(fn () => $snipeIt->modelOptions())
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Asset model from Snipe-IT.'),
                    Forms\Components\TextInput::make('name')
                        ->label('Asset Name')
                        ->required()
                        ->maxLength(255)
                        ->default(fn () => $receipt?->name ?? $receipt?->asset_description ?? $receipt?->item?->name)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('serial_number')
                        ->label('Serial')
                        ->maxLength(255)
                        ->helperText('Click the check icon to verify this serial is unique in Snipe-IT.')
                        ->suffixAction(
                            Action::make('checkSerialUnique')
                                ->icon('heroicon-o-shield-check')
                                ->tooltip('Check serial uniqueness in Snipe-IT')
                                ->action(function (Get $get) use ($snipeIt, $receipt): void {
                                    try {
                                        $result = $snipeIt->checkSerialNumber(
                                            (string) $get('serial_number'),
                                            $receipt?->snipe_it_hardware_id,
                                        );
                                    } catch (SnipeItException $exception) {
                                        Notification::make()
                                            ->title('Serial check failed')
                                            ->body($exception->getMessage())
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    $notification = Notification::make()
                                        ->title($result->isAvailable ? 'Serial available' : 'Serial already in use')
                                        ->body($result->message);

                                    if ($result->isAvailable) {
                                        $notification->success();
                                    } else {
                                        $notification->warning();
                                    }

                                    $notification->send();
                                })
                        ),
                    Forms\Components\Select::make('snipe_location_id')
                        ->label('Location')
                        ->options(fn () => $snipeIt->locationOptions())
                        ->native(false)
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('order_number')
                        ->label('Order Number')
                        ->maxLength(255)
                        ->default(fn () => $receipt?->order_number ?? $receipt?->invoice_number ?? $receipt?->purchaseOrder?->po_no),
                    Forms\Components\DatePicker::make('purchase_date')
                        ->label('Purchase Date')
                        ->native(false)
                        ->default(fn () => $receipt?->purchase_date ?? $receipt?->purchaseOrder?->date),
                    Forms\Components\TextInput::make('purchase_cost')
                        ->label('Purchase Cost')
                        ->numeric()
                        ->prefix('MVR')
                        ->default(fn () => $receipt?->purchase_cost ?? $receipt?->purchaseOrderDetail?->amount),
                    Forms\Components\Select::make('snipe_supplier_id')
                        ->label('Supplier')
                        ->options(fn () => $snipeIt->supplierOptions())
                        ->native(false)
                        ->searchable()
                        ->preload(),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Snipe-IT Custom Fields')
                ->description('Matches your Snipe-IT fieldset. CAO Asset Code is required in Snipe-IT.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('cao_asset_code')
                        ->label('CAO Asset Code')
                        ->required()
                        ->maxLength(255)
                        ->default(fn () => $receipt?->cao_asset_code),
                    Forms\Components\TextInput::make('finance_old_asset_tag')
                        ->label('Finance Old Asset Tag No.')
                        ->maxLength(255)
                        ->default(fn () => $receipt?->finance_old_asset_tag),
                    Forms\Components\TextInput::make('asset_class')
                        ->label('Asset Class')
                        ->maxLength(255)
                        ->default(fn () => $receipt?->asset_class),
                    Forms\Components\TextInput::make('mac_address')
                        ->label('MAC Address')
                        ->maxLength(255)
                        ->placeholder('AA:BB:CC:DD:EE:FF')
                        ->rules(['nullable', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'])
                        ->default(fn () => $receipt?->mac_address),
                    Forms\Components\Placeholder::make('po_reference_info')
                        ->label('PO number [Reference No]')
                        ->content('Sent to Snipe-IT from Order Number above.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function defaultFill(AssetReceipt $receipt): array
    {
        return [
            'name' => $receipt->name ?? $receipt->asset_description ?? $receipt->item?->name,
            'serial_number' => $receipt->serial_number,
            'snipe_model_id' => $receipt->snipe_model_id,
            'snipe_status_id' => $receipt->snipe_status_id ?? config('snipe-it.default_status_id'),
            'snipe_location_id' => $receipt->snipe_location_id,
            'snipe_supplier_id' => $receipt->snipe_supplier_id,
            'order_number' => $receipt->order_number ?? $receipt->invoice_number ?? $receipt->purchaseOrder?->po_no,
            'purchase_date' => $receipt->purchase_date ?? $receipt->purchaseOrder?->date,
            'purchase_cost' => $receipt->purchase_cost ?? $receipt->purchaseOrderDetail?->amount,
            'notes' => $receipt->notes,
            'cao_asset_code' => $receipt->cao_asset_code,
            'finance_old_asset_tag' => $receipt->finance_old_asset_tag,
            'asset_class' => $receipt->asset_class,
            'mac_address' => $receipt->mac_address,
        ];
    }
}
