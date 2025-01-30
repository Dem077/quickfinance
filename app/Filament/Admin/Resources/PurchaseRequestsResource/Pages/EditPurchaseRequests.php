<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;

use App\Filament\Admin\Resources\PurchaseRequestsResource;
use App\Models\PurchaseRequests;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditPurchaseRequests extends EditRecord
{
    protected static string $resource = PurchaseRequestsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('submit_for_approval')
            ->label('Submit for Approval')
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->visible(fn ($record) => 
                !$record->is_submited && 
                Auth::user()->can('send_approval_purchase::requests')
            )
            ->action(function (PurchaseRequests $record) {
                $record->update([
                    'is_submited' => true,
                ]);
                Notification::make()
                    ->title('Submitted for approval successfully')
                    ->success()
                    ->send();
            }),
           Actions\Action::make('approve_purchase_request')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => 
                    $record->is_submited &&
                    !$record->is_canceled && 
                    !$record->is_approved && 
                    Auth::user()->can('approve_purchase::requests')
                )
                ->action(function (PurchaseRequests $record) {
                    $record->update([
                        'is_approved' => true,
                    ]);
                    Notification::make()
                        ->title('PR Approved successfully')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('cancel_purchase_request')
                ->label('Cancel')
                ->icon('heroicon-o-check-circle')
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make('cancel_remark')
                        ->label('Cancellation Reason')
                        ->required()
                        ->maxLength(255)
                ])
                ->visible(fn ($record) => 
                    $record->is_submited &&
                    !$record->is_approved && 
                    Auth::user()->can('approve_purchase::requests')
                )
                ->action(function (PurchaseRequests $record ,array $data) {
                    $record->update([
                        'is_canceled' => true,
                        'cancel_remark' => $data['cancel_remark']
                    ]);
                    Notification::make()
                        ->title('PR Approved successfully')
                        ->success()
                        ->send();
                }),
        ];
    }


}
