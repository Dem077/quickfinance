<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestsStatus;
use App\Filament\Admin\Resources\PurchaseRequestsResource;
use App\Mail\NotificationEmail;
use App\Mail\StatusEmail;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequests;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
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

            Actions\Action::make('submit_for_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => $this->record->status == PurchaseRequestsStatus::Draft &&
                    Auth::user()->can('send_approval_purchase::requests')
                )
                ->action(function ( User $user) {
                    $this->record->update([
                        'status' => PurchaseRequestsStatus::Submitted,
                        // 'is_submited' => true,
                    ]);

                    $hod =   $this->record->user->department->user->email;

                    Mail::to($hod)->queue(new NotificationEmail('Purchase Request '.  $this->record->pr_no));

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
                    $user = User::find($record->user_id);
                    Mail::to($user->email)->queue(new StatusEmail('Purchase Request '.$record->pr_no, 'canceled', $data['cancel_remark'], ''));
                    Notification::make()
                        ->title('PR Canceled successfully')
                        ->warning()
                        ->send();
                }),
            Actions\Action::make('approve_purchase_close')
                ->label('Close')
                ->icon('heroicon-o-check-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Are you sure you want to close this PR? This action cannot be undone.')
                ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::DocumentUploaded &&
                    Auth::user()->can('approve_purchase::requests')
                )
                ->action(function (PurchaseRequests $record) {

                    $record->update([
                        'status' => PurchaseRequestsStatus::Closed,
                        'is_closed_by' => Auth::id(),
                    ]);
                    $po = PurchaseOrders::where('is_closed', false)->where('pr_id', $record->id)->get();
                    if (! $po) {
                        foreach ($po as $p) {
                            $p->update([
                                'status' => PurchaseOrderStatus::Closed,
                                'is_closed' => true,
                                'is_closed_by' => Auth::id(),
                            ]);
                        }
                    }

                    Notification::make()
                        ->title('PR Closed successfully')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('upload_document')
                ->label('Upload Document')
                ->icon('heroicon-o-document')
                ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Approved && Auth::user()->can('send_approval_purchase::requests'))
                ->form([
                    FileUpload::make('uploaded_document')
                        ->label('Document')
                        ->required(),
                ])
                ->action(function (PurchaseRequests $record, array $data) {
                    $record->update([
                        'status' => PurchaseRequestsStatus::DocumentUploaded,
                        'uploaded_document' => $data['uploaded_document'],
                    ]);
                    Notification::make()
                        ->title('Document uploaded successfully')
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
                ->visible(fn ($record) => $record->status == PurchaseRequestsStatus::Approved && Auth::user()->can('send_approval_purchase::requests'))
                ->url(fn (PurchaseRequests $record) => route('purchase-requests.download', $record))
                ->openUrlInNewTab(),

            Actions\EditAction::make()
                ->visible(fn ($record) => ($record->status == PurchaseRequestsStatus::HODApproved && Auth::user()->can('approve_purchase::requests')) || ($record->status == PurchaseRequestsStatus::Draft && Auth::user()->can('send_approval_purchase::requests')) || ($record->status == PurchaseRequestsStatus::Submitted && $record->user->department->user->id == Auth::user()->id)),
        ];
    }
}
