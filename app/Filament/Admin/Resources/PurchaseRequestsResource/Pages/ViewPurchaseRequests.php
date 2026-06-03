<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;

use App\Enums\PurchaseRequestsStatus;
use App\Filament\Admin\Resources\PurchaseRequestsResource;
use App\Mail\NotificationEmail;
use App\Mail\StatusEmail;
use App\Models\PurchaseRequests;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class ViewPurchaseRequests extends ViewRecord
{
    protected static string $resource = PurchaseRequestsResource::class;

    protected function getHeaderActions(): array
    {
        return [

            Actions\DeleteAction::make()
                ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Draft),
            Actions\Action::make('submit_for_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => $this->record->status == PurchaseRequestsStatus::Draft &&
                    Auth::user()->can('send_approval_purchase::requests')
                )
                ->action(function (User $user) {
                    $this->record->update([
                        'status' => PurchaseRequestsStatus::Submitted,
                        // 'is_submited' => true,
                    ]);

                    $hod = $this->record->user->department->user->email;

                    Mail::to($hod)->queue(new NotificationEmail('Purchase Request '.$this->record->pr_no));

                    Notification::make()
                        ->title('Submitted for approval successfully')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('approve_purchase_request_hod')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Submitted && $record->user->department->user->id == Auth::user()->id
                )
                ->action(function (PurchaseRequests $record) {
                    $record->update([
                        'approved_by_hod' => Auth::id(),
                        'status' => PurchaseRequestsStatus::HODApproved,
                    ]);
                    $user = User::find($record->user_id);
                    Mail::to($user->email)->queue(new StatusEmail('Purchase Request '.$record->pr_no, 'approved', '', 'HOD'));

                    Notification::make()
                        ->title('PR Approved successfully')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('reject_purchase_request_hod')
                ->label('Reject')
                ->icon('heroicon-o-check-circle')
                ->color('danger')
                ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Submitted && $record->user->department->user->id == Auth::user()->id
                )
                ->action(function (PurchaseRequests $record) {
                    $record->update([
                        'is_approved_by_hod' => true,
                        'approved_by_hod' => Auth::id(), // Actually to record who rejected
                        'status' => PurchaseRequestsStatus::HODRejected,
                    ]);
                    $user = User::find($record->user_id);
                    Mail::to($user->email)->queue(new StatusEmail('Purchase Request '.$record->pr_no, 'rejected', '', 'HOD'));

                    Notification::make()
                        ->title('PR Approved successfully')
                        ->success()
                        ->send();
                }),

            // Finance Approval
            Actions\Action::make('approve_purchase_request')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::HODApproved &&
                    Auth::user()->can('approve_purchase::requests')
                )
                ->action(function (PurchaseRequests $record) {
                    $record->update([
                        'status' => PurchaseRequestsStatus::Approved,
                        'approved_canceled_by' => Auth::id(),
                    ]);
                    $user = User::find($record->user_id);
                    Mail::to($user->email)->queue(new StatusEmail('Purchase Request '.$record->pr_no, 'approved', '', 'Finance'));

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
                    Textarea::make('cancel_remark')
                        ->label('Cancellation Reason')
                        ->required()
                        ->maxLength(255),
                ])
                ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::HODApproved &&
                    Auth::user()->can('approve_purchase::requests')
                )
                ->action(function (PurchaseRequests $record, array $data) {
                    $record->update([
                        'status' => PurchaseRequestsStatus::Canceled,
                        // 'is_canceled' => true,
                        'cancel_remark' => $data['cancel_remark'],
                        'approved_canceled_by' => Auth::id(),
                    ]);

                    Notification::make()
                        ->title('PR Canceled successfully')
                        ->warning()
                        ->send();
                }),
            Actions\Action::make('send_back_purchase_request')
                ->label('Send Back To Draft')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::HODApproved &&
                    Auth::user()->can('approve_purchase::requests')
                )
                ->action(function (PurchaseRequests $record, array $data) {
                    $record->update([
                        'status' => PurchaseRequestsStatus::Draft,
                        // 'is_canceled' => true,
                    ]);

                    Notification::make()
                        ->title('PR Canceled successfully')
                        ->warning()
                        ->send();
                }),
            Actions\Action::make('approve_purchase_close')
                ->label('Close')
                ->icon('heroicon-o-check-circle')
                ->color('danger')
                ->modalHeading('Close Purchase Request')
                ->modalDescription(fn (PurchaseRequests $record) => $record->openPurchaseOrdersForClose()->isNotEmpty()
                    ? 'Enter GRN numbers for related purchase orders. The PR and those purchase orders will be closed.'
                    : 'Are you sure you want to close this PR? This action cannot be undone.')
                ->form(fn (PurchaseRequests $record): array => PurchaseRequestsResource::getCloseFormSchema($record))
                ->requiresConfirmation(fn (PurchaseRequests $record) => $record->openPurchaseOrdersForClose()->isEmpty())
                ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::MD_DMD_Approved &&
                    Auth::user()->can('close_purchase::requests')
                )
                ->action(function (PurchaseRequests $record, array $data) {
                    PurchaseRequestsResource::closePurchaseRequest($record, $data);

                    Notification::make()
                        ->title('PR Closed successfully')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('approve_purchase_request_md_dmd')
                ->label('MD / DMD Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Approved &&
                    Auth::user()->can('md_dmd_approve_purchase::requests')
                )
                ->action(function (PurchaseRequests $record) {
                    $record->update([
                        'status' => PurchaseRequestsStatus::MD_DMD_Approved,
                        'approved_canceled_by' => Auth::id(),
                        'approved_by_md_dmd' => Auth::id(),
                    ]);
                    Notification::make()
                        ->title('PR approved by MD / DMD successfully')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('view_document')
                ->label('View Document')
                ->icon('heroicon-o-eye')
                ->visible(fn ($record) => $record->uploaded_document)
                ->url(fn ($record) => asset('storage/'.$record->uploaded_document))
                ->openUrlInNewTab(),

            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-eye')
                ->visible(fn ($record) => in_array($record->status, [PurchaseRequestsStatus::Approved, PurchaseRequestsStatus::MD_DMD_Approved]) && Auth::user()->can('send_approval_purchase::requests'))
                ->url(fn (PurchaseRequests $record) => route('purchase-requests.download', $record))
                ->openUrlInNewTab(),

            Actions\EditAction::make()
                ->visible(fn ($record) => ($record->status == PurchaseRequestsStatus::HODApproved && Auth::user()->can('approve_purchase::requests')) || ($record->status == PurchaseRequestsStatus::Draft && Auth::user()->can('send_approval_purchase::requests')) || ($record->status == PurchaseRequestsStatus::Submitted && $record->user->department->user->id == Auth::user()->id)),
        ];
    }
}
