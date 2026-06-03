<?php

namespace App\Filament\Admin\Resources\AssetManagementResource\RelationManagers;

use App\Enums\AssetReceiptStatus;
use App\Filament\Forms\SnipeItHardwareForm;
use App\Models\AssetReceipt;
use App\Services\SnipeIt\SnipeItException;
use App\Services\SnipeIt\SnipeItService;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

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
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Finance Item')
                    ->searchable(),
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
                        $attributes = [
                            'name' => $data['name'] ?? null,
                            'asset_description' => $data['name'] ?? null,
                            'serial_number' => $data['serial_number'] ?? null,
                            'snipe_model_id' => $data['snipe_model_id'] ?? null,
                            'snipe_status_id' => $data['snipe_status_id'] ?? null,
                            'snipe_location_id' => $data['snipe_location_id'] ?? null,
                            'snipe_supplier_id' => $data['snipe_supplier_id'] ?? null,
                            'order_number' => $data['order_number'] ?? null,
                            'invoice_number' => $data['order_number'] ?? null,
                            'purchase_date' => $data['purchase_date'] ?? null,
                            'purchase_cost' => $data['purchase_cost'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'cao_asset_code' => $data['cao_asset_code'] ?? null,
                            'finance_old_asset_tag' => $data['finance_old_asset_tag'] ?? null,
                            'asset_class' => $data['asset_class'] ?? null,
                            'mac_address' => $data['mac_address'] ?? null,
                        ];

                        try {
                            $created = app(SnipeItService::class)->createAssetFromReceipt(
                                $record->fill($attributes)
                            );
                        } catch (SnipeItException $exception) {
                            Notification::make()
                                ->title('Snipe-IT sync failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            ...$attributes,
                            'asset_tag' => $created->assetTag,
                            'snipe_it_hardware_id' => $created->hardwareId,
                            'status' => AssetReceiptStatus::Received,
                            'received_by' => Auth::id(),
                            'received_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Asset item marked as received')
                            ->body('Asset created in Snipe-IT (tag: '.$created->assetTag.', ID: '.$created->hardwareId.').')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
