<?php

namespace App\Filament\Admin\Resources\AssetManagementResource\Pages;

use App\Filament\Admin\Resources\AssetManagementResource;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAssetManagement extends ViewRecord
{
    protected static string $resource = AssetManagementResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->syncAssetReceipts();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Purchase Order')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('po_no')
                                    ->label('PO Number'),
                                TextEntry::make('purchaseRequest.pr_no')
                                    ->label('PR Number'),
                                TextEntry::make('vendor.name')
                                    ->label('Vendor'),
                                TextEntry::make('date')
                                    ->date(),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('asset_status')
                                    ->label('Asset Status')
                                    ->badge()
                                    ->getStateUsing(fn ($record): string => $record->hasPendingAssetReceipts()
                                        ? 'Pending'
                                        : 'Completed')
                                    ->color(fn ($record): string => $record->hasPendingAssetReceipts()
                                        ? 'warning'
                                        : 'success'),
                            ]),
                    ]),
            ]);
    }
}
