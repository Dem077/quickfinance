<?php

namespace App\Filament\Admin\Resources\AssetManagementResource\RelationManagers;

use App\Enums\AssetReceiptStatus;
use App\Enums\ItemTypeEnum;
use App\Filament\Forms\SnipeItAccessoryForm;
use App\Filament\Forms\SnipeItHardwareForm;
use App\Models\AssetReceipt;
use App\Services\AssetReceipt\AssetReceiptReceiver;
use App\Services\SnipeIt\SnipeItException;
use App\Services\SnipeIt\SnipeItService;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AssetReceiptsRelationManager extends RelationManager
{
    protected static string $relationship = 'assetReceipts';

    protected static ?string $title = 'Snipe-IT Items';

    public function form(Form $form): Form
    {
        return $form->schema(SnipeItHardwareForm::schema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with('item')
                ->orderBy('purchase_order_detail_id')
                ->orderBy('unit_index'))
            ->recordTitle(fn (AssetReceipt $record): string => collect([
                $record->item?->name,
                $record->unitLabel(),
            ])->filter()->implode(' — '))
            ->columns([
                Tables\Columns\TextColumn::make('item.type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Finance Item')
                    ->searchable(),
                Tables\Columns\TextColumn::make('unit_index')
                    ->label('Unit / Qty')
                    ->formatStateUsing(fn ($state, AssetReceipt $record): string => $record->unitLabel() ?? '1'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('asset_tag')
                    ->label('Asset Tag')
                    ->placeholder('—')
                    ->visible(fn (): bool => true),
                Tables\Columns\TextColumn::make('snipe_quantity')
                    ->label('Snipe Qty')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('snipe_model_id')
                    ->label('Model ID')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('snipe_category_id')
                    ->label('Category ID')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('snipe_it_hardware_id')
                    ->label('Snipe Asset ID')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('snipe_it_accessory_id')
                    ->label('Snipe Accessory ID')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('received_at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('item_received')
                    ->label(fn (AssetReceipt $record): string => $record->isAccessoryLine() ? 'Accessory Received' : 'Item Received')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->visible(fn (AssetReceipt $record): bool => $record->status === AssetReceiptStatus::Pending)
                    ->form(fn (Form $form, AssetReceipt $record): Form => $form->schema(
                        $record->isAccessoryLine()
                            ? SnipeItAccessoryForm::schema($record)
                            : SnipeItHardwareForm::schema($record)
                    ))
                    ->fillForm(fn (AssetReceipt $record): array => $record->isAccessoryLine()
                        ? SnipeItAccessoryForm::defaultFill($record)
                        : SnipeItHardwareForm::defaultFill($record))
                    ->action(function (AssetReceipt $record, array $data): void {
                        $receiver = app(AssetReceiptReceiver::class);
                        $type = $record->loadMissing('item')->item?->type ?? ItemTypeEnum::Asset;
                        $attributes = $receiver->attributesFromFormData($data, type: $type);

                        try {
                            $created = $receiver->receive($record, $attributes);
                        } catch (SnipeItException $exception) {
                            Notification::make()
                                ->title('Snipe-IT sync failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Item marked as received')
                            ->body('Created in Snipe-IT ('.$created->summaryLabel().').')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_receive_assets')
                        ->label('Receive selected assets in Snipe-IT')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (): bool => app(SnipeItService::class)->isEnabled())
                        ->before(function (Tables\Actions\BulkAction $action, Collection $records): void {
                            $pendingAssets = $records->filter(
                                fn (AssetReceipt $record): bool => $record->status === AssetReceiptStatus::Pending
                                    && ! $record->isAccessoryLine()
                            );

                            if ($pendingAssets->isNotEmpty()) {
                                return;
                            }

                            Notification::make()
                                ->title('No pending assets selected')
                                ->body('Select pending asset (hardware) units only for this bulk action.')
                                ->warning()
                                ->send();

                            $action->halt();
                        })
                        ->form(SnipeItHardwareForm::bulkReceiveSchema())
                        ->fillForm(fn (Collection $records): array => SnipeItHardwareForm::bulkDefaultFill(
                            $records
                                ->filter(fn (AssetReceipt $record): bool => $record->status === AssetReceiptStatus::Pending
                                    && ! $record->isAccessoryLine())
                                ->load(['item', 'purchaseOrder', 'purchaseOrderDetail'])
                        ))
                        ->action(fn (Collection $records, array $data) => $this->processBulkReceive(
                            $records,
                            $data,
                            ItemTypeEnum::Asset,
                            'assets'
                        ))
                        ->deselectRecordsAfterCompletion()
                        ->closeModalByClickingAway(false),
                    Tables\Actions\BulkAction::make('bulk_receive_accessories')
                        ->label('Receive selected accessories in Snipe-IT')
                        ->icon('heroicon-o-squares-plus')
                        ->color('info')
                        ->visible(fn (): bool => app(SnipeItService::class)->isEnabled())
                        ->before(function (Tables\Actions\BulkAction $action, Collection $records): void {
                            $pending = $records->filter(
                                fn (AssetReceipt $record): bool => $record->status === AssetReceiptStatus::Pending
                                    && $record->isAccessoryLine()
                            );

                            if ($pending->isNotEmpty()) {
                                return;
                            }

                            Notification::make()
                                ->title('No pending accessories selected')
                                ->body('Select pending accessory lines only for this bulk action.')
                                ->warning()
                                ->send();

                            $action->halt();
                        })
                        ->form(SnipeItAccessoryForm::bulkReceiveSchema())
                        ->fillForm(fn (Collection $records): array => SnipeItAccessoryForm::bulkDefaultFill(
                            $records
                                ->filter(fn (AssetReceipt $record): bool => $record->status === AssetReceiptStatus::Pending
                                    && $record->isAccessoryLine())
                                ->load(['item', 'purchaseOrder', 'purchaseOrderDetail'])
                        ))
                        ->action(fn (Collection $records, array $data) => $this->processBulkReceive(
                            $records,
                            $data,
                            ItemTypeEnum::Accessory,
                            'accessories'
                        ))
                        ->deselectRecordsAfterCompletion()
                        ->closeModalByClickingAway(false),
                ]),
            ]);
    }

    /**
     * @param  'assets'|'accessories'  $repeaterKey
     */
    protected function processBulkReceive(Collection $records, array $data, ItemTypeEnum $type, string $repeaterKey): void
    {
        $pending = $records->filter(function (AssetReceipt $record) use ($type): bool {
            $record->loadMissing('item');

            return $record->status === AssetReceiptStatus::Pending
                && $record->item?->type === $type;
        });

        if ($pending->isEmpty()) {
            Notification::make()
                ->title('No pending items to process')
                ->warning()
                ->send();

            return;
        }

        $receiver = app(AssetReceiptReceiver::class);
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
                $receiver->receive(
                    $receipt,
                    $receiver->attributesFromFormData($shared, $row, $type)
                );
                $succeeded++;
            } catch (SnipeItException $exception) {
                $label = $row['unit_label'] ?? $row['line_label'] ?? 'Item #'.$receiptId;
                $failures[] = $label.': '.$exception->getMessage();
            }
        }

        $itemLabel = $type === ItemTypeEnum::Accessory ? 'accessory line(s)' : 'asset(s)';

        if ($succeeded > 0 && $failures === []) {
            Notification::make()
                ->title('Bulk receive completed')
                ->body($succeeded.' '.$itemLabel.' created in Snipe-IT.')
                ->success()
                ->send();

            return;
        }

        if ($succeeded > 0) {
            Notification::make()
                ->title('Bulk receive partially completed')
                ->body($succeeded.' succeeded. '.count($failures).' failed: '.Str::limit(implode(' | ', $failures), 500))
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Bulk receive failed')
            ->body(Str::limit(implode(' | ', $failures), 500))
            ->danger()
            ->send();
    }
}
