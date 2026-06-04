<?php

namespace App\Filament\Admin\Resources\AssetManagementResource\RelationManagers;

use App\Enums\AssetReceiptStatus;
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

    protected static ?string $title = 'Asset Items';

    public function form(Form $form): Form
    {
        return $form->schema(SnipeItHardwareForm::schema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->orderBy('purchase_order_detail_id')
                ->orderBy('unit_index'))
            ->recordTitle(fn (AssetReceipt $record): string => collect([
                $record->item?->name,
                $record->unitLabel(),
            ])->filter()->implode(' — '))
            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Finance Item')
                    ->searchable(),
                Tables\Columns\TextColumn::make('unit_index')
                    ->label('Unit')
                    ->formatStateUsing(fn ($state, AssetReceipt $record): string => $record->unitLabel() ?? '1')
                    ->sortable(),
                Tables\Columns\TextColumn::make('purchaseOrderDetail.qty')
                    ->label('PO Qty')
                    ->numeric(decimalPlaces: 0),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('asset_tag')
                    ->label('Asset Tag')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('cao_asset_code')
                    ->label('CAO Asset Code')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Asset Name')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('snipe_model_id')
                    ->label('Model ID')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order Number')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('purchase_cost')
                    ->label('Purchase Cost')
                    ->money('MVR')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('snipe_it_hardware_id')
                    ->label('Snipe-IT ID')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('received_at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('item_received')
                    ->label('Item Received')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->visible(fn (AssetReceipt $record): bool => $record->status === AssetReceiptStatus::Pending)
                    ->form(fn (Form $form, AssetReceipt $record): Form => $form->schema(
                        SnipeItHardwareForm::schema($record)
                    ))
                    ->fillForm(fn (AssetReceipt $record): array => SnipeItHardwareForm::defaultFill($record))
                    ->action(function (AssetReceipt $record, array $data): void {
                        $receiver = app(AssetReceiptReceiver::class);
                        $attributes = $receiver->attributesFromFormData($data);

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
                            ->title('Asset item marked as received')
                            ->body('Asset created in Snipe-IT (tag: '.$created->assetTag.', ID: '.$created->hardwareId.').')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_receive')
                        ->label('Receive selected in Snipe-IT')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (): bool => app(SnipeItService::class)->isEnabled())
                        ->before(function (Tables\Actions\BulkAction $action, Collection $records): void {
                            if ($records->contains(fn (AssetReceipt $record): bool => $record->status === AssetReceiptStatus::Pending)) {
                                return;
                            }

                            Notification::make()
                                ->title('No pending units selected')
                                ->body('Select at least one pending asset unit to receive in bulk.')
                                ->warning()
                                ->send();

                            $action->halt();
                        })
                        ->form(SnipeItHardwareForm::bulkReceiveSchema())
                        ->fillForm(fn (Collection $records): array => SnipeItHardwareForm::bulkDefaultFill(
                            $records
                                ->filter(fn (AssetReceipt $record): bool => $record->status === AssetReceiptStatus::Pending)
                                ->load(['item', 'purchaseOrder', 'purchaseOrderDetail'])
                        ))
                        ->action(function (Collection $records, array $data): void {
                            $pending = $records->filter(
                                fn (AssetReceipt $record): bool => $record->status === AssetReceiptStatus::Pending
                            );

                            if ($pending->isEmpty()) {
                                Notification::make()
                                    ->title('No pending units selected')
                                    ->body('Only pending asset units can be received in bulk.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            if ($pending->count() !== $records->count()) {
                                Notification::make()
                                    ->title('Some units were skipped')
                                    ->body('Already received units were ignored. Processing '.$pending->count().' pending unit(s).')
                                    ->warning()
                                    ->send();
                            }

                            $receiver = app(AssetReceiptReceiver::class);
                            $shared = collect($data)->except('assets')->all();
                            $succeeded = 0;
                            $failures = [];

                            foreach ($data['assets'] ?? [] as $row) {
                                $receiptId = (int) ($row['asset_receipt_id'] ?? 0);
                                $receipt = $pending->firstWhere('id', $receiptId);

                                if (! $receipt) {
                                    continue;
                                }

                                try {
                                    $receiver->receive(
                                        $receipt,
                                        $receiver->attributesFromFormData($shared, $row)
                                    );
                                    $succeeded++;
                                } catch (SnipeItException $exception) {
                                    $label = $row['unit_label'] ?? 'Unit #'.$receiptId;
                                    $failures[] = $label.': '.$exception->getMessage();
                                }
                            }

                            if ($succeeded > 0 && $failures === []) {
                                Notification::make()
                                    ->title('Bulk receive completed')
                                    ->body($succeeded.' asset(s) created in Snipe-IT.')
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
                        })
                        ->deselectRecordsAfterCompletion()
                        ->closeModalByClickingAway(false),
                ]),
            ]);
    }
}
