<?php

namespace App\Filament\Admin\Resources;

use App\Enums\AssetReceiptStatus;
use App\Enums\ItemTypeEnum;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Admin\Resources\AssetManagementResource\Pages;
use App\Filament\Admin\Resources\AssetManagementResource\RelationManagers;
use App\Models\PurchaseOrders;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetManagementResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = PurchaseOrders::class;

    protected static ?string $policy = \App\Policies\AssetManagementPolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Record Management';

    protected static ?string $navigationLabel = 'Asset Management';

    protected static ?string $modelLabel = 'asset record';

    protected static ?string $pluralModelLabel = 'asset management';

    protected static ?string $slug = 'asset-management';

    protected static ?int $navigationSort = 4;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('payment_method', 'purchase_order')
            ->whereIn('status', [
                PurchaseOrderStatus::Submitted,
                PurchaseOrderStatus::Closed,
            ])
            ->where(function (Builder $query): void {
                $query->whereHas('assetReceipts')
                    ->orWhereHas('purchaseOrderDetails', function (Builder $detailQuery): void {
                        $detailQuery->whereHas('items', fn (Builder $itemQuery): Builder => $itemQuery
                            ->where('type', ItemTypeEnum::Asset));
                    });
            })
            ->with([
                'vendor',
                'purchaseRequest',
                'assetReceipts.item',
                'assetReceipts.purchaseOrderDetail',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('po_no')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('purchaseRequest.pr_no')
                    ->label('PR Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('PO Status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('asset_status')
                    ->label('Asset Status')
                    ->badge()
                    ->getStateUsing(fn (PurchaseOrders $record): string => $record->hasPendingAssetReceipts()
                        ? 'Pending'
                        : 'Completed')
                    ->color(fn (PurchaseOrders $record): string => $record->hasPendingAssetReceipts()
                        ? 'warning'
                        : 'success'),
                Tables\Columns\TextColumn::make('pending_assets_count')
                    ->label('Pending Items')
                    ->getStateUsing(fn (PurchaseOrders $record): int => $record->assetReceipts
                        ->where('status', AssetReceiptStatus::Pending)
                        ->count()),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('asset_status')
                    ->label('Asset Status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'pending' => $query->whereHas('assetReceipts', fn (Builder $q) => $q
                                ->where('status', AssetReceiptStatus::Pending)),
                            'completed' => $query->whereDoesntHave('assetReceipts', fn (Builder $q) => $q
                                ->where('status', AssetReceiptStatus::Pending)),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->button(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AssetReceiptsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetManagement::route('/'),
            'view' => Pages\ViewAssetManagement::route('/{record}'),
        ];
    }
}
