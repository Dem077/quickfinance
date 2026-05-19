<?php

namespace App\Filament\Admin\Resources\PurchaseRequestsResource\Pages;

use App\Enums\PurchaseRequestsStatus;
use App\Filament\Admin\Resources\PurchaseRequestsResource;
use App\Models\PurchaseRequests;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [];

        // this is for USer
        // Draft tab - only for users who can create requests
        if (Auth::user()->can('send_approval_purchase::requests')) {
            $tabs['all'] = Tab::make('All')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', Auth::user()->id))
                ->badge(PurchaseRequests::query()->where('user_id', Auth::user()->id)->count())
                ->icon('heroicon-o-rectangle-stack');
            $tabs['draft'] = Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestsStatus::Draft)->where('user_id', Auth::user()->id))
                ->badge(PurchaseRequests::query()->where('status', PurchaseRequestsStatus::Draft)->where('user_id', Auth::user()->id)->count())
                ->badgeColor('gray')
                ->icon('heroicon-o-document');

            $tabs['submitted'] = Tab::make('Awaiting Approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', [PurchaseRequestsStatus::Submitted, PurchaseRequestsStatus::HODApproved])->where('user_id', Auth::user()->id))
                ->badge(PurchaseRequests::query()->where('status', [PurchaseRequestsStatus::Submitted, PurchaseRequestsStatus::HODApproved])->where('user_id', Auth::user()->id)->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-paper-airplane');

            $tabs['awaiting'] = Tab::make('Action Required')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestsStatus::Approved)->where('user_id', Auth::user()->id))
                ->badge(PurchaseRequests::query()->where('status', PurchaseRequestsStatus::Approved)->where('user_id', Auth::user()->id)->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-exclamation-circle');

            $tabs['procurement'] = Tab::make('Pending Procurement')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestsStatus::MD_DMD_Approved)->where('user_id', Auth::user()->id))
                ->badge(PurchaseRequests::query()->where('status', PurchaseRequestsStatus::MD_DMD_Approved)->where('user_id', Auth::user()->id)->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-exclamation-circle');

            $tabs['completed'] = Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestsStatus::Closed)->where('user_id', Auth::user()->id))
                ->badge(PurchaseRequests::query()->where('status', PurchaseRequestsStatus::Closed)->where('user_id', Auth::user()->id)->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle');

            $tabs['rejected'] = Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', [PurchaseRequestsStatus::HODRejected, PurchaseRequestsStatus::Canceled])->where('user_id', Auth::user()->id))
                ->badge(PurchaseRequests::query()->where('status', [PurchaseRequestsStatus::HODRejected, PurchaseRequestsStatus::Canceled])->where('user_id', Auth::user()->id)->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle');
        }

        //  HODs and approvers Tab
        if (Auth::user()->hodof()->exists()) {
            $tabs['all'] = Tab::make('All')
                ->badge(PurchaseRequests::query()->count())
                ->icon('heroicon-o-rectangle-stack');

            $tabs['hodsubmitted'] = Tab::make('Awaiting Approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestsStatus::Submitted))
                ->badge(PurchaseRequests::query()->where('status', PurchaseRequestsStatus::Submitted)->count())
                ->badgeColor('draft')
                ->icon('heroicon-o-paper-airplane');

            $tabs['hodrejected'] = Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', [PurchaseRequestsStatus::HODRejected, PurchaseRequestsStatus::Canceled]))
                ->badge(PurchaseRequests::query()->where('status', [PurchaseRequestsStatus::HODRejected, PurchaseRequestsStatus::Canceled])->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle');

            $tabs['hodprocurement'] = Tab::make('Pending Procurement')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestsStatus::MD_DMD_Approved)->where('user_id', Auth::user()->id))
                ->badge(PurchaseRequests::query()->where('status', PurchaseRequestsStatus::MD_DMD_Approved)->where('user_id', Auth::user()->id)->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-exclamation-circle');

            $tabs['hodcompleted'] = Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestsStatus::Closed)->where('user_id', Auth::user()->id))
                ->badge(PurchaseRequests::query()->where('status', PurchaseRequestsStatus::Closed)->where('user_id', Auth::user()->id)->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle');
        }

        // finance
        if (Auth::user()->can('approve_purchase::requests')) {
            $tabs['all'] = Tab::make('All')
                ->badge(PurchaseRequests::query()->count())
                ->icon('heroicon-o-rectangle-stack');

            $tabs['finance_approval'] = Tab::make('Pending Approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    PurchaseRequestsStatus::HODApproved,
                ]))
                ->badge(PurchaseRequests::query()->whereIn('status', [
                    PurchaseRequestsStatus::HODApproved,
                ])->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-user-circle');

            $tabs['finance_rejected'] = Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    PurchaseRequestsStatus::Canceled,
                ]))
                ->badge(PurchaseRequests::query()->whereIn('status', [
                    PurchaseRequestsStatus::Canceled,
                ])->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-user-circle');

            $tabs['finance_approved'] = Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    PurchaseRequestsStatus::Approved,
                ]))
                ->badge(PurchaseRequests::query()->whereIn('status', [
                    PurchaseRequestsStatus::Approved,
                ])->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle');

            $tabs['finance_completed'] = Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    PurchaseRequestsStatus::Closed,
                ]))
                ->badge(PurchaseRequests::query()->whereIn('status', [
                    PurchaseRequestsStatus::Closed,
                ])->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-check-badge');
        }

        // MD / DMD approvers
        if (Auth::user()->can('md_dmd_approve_purchase::requests')) {
            $mdDmdStatuses = [
                PurchaseRequestsStatus::Approved,
                PurchaseRequestsStatus::MD_DMD_Approved,
                PurchaseRequestsStatus::MD_DMD_Rejected,
                PurchaseRequestsStatus::Closed,
            ];

            $tabs['all'] = Tab::make('All')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $mdDmdStatuses))
                ->badge(PurchaseRequests::query()->whereIn('status', $mdDmdStatuses)->count())
                ->icon('heroicon-o-rectangle-stack');

            $tabs['md_dmd_pending'] = Tab::make('Pending Approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestsStatus::Approved))
                ->badge(PurchaseRequests::query()->where('status', PurchaseRequestsStatus::Approved)->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-clock');

            $tabs['md_dmd_approved'] = Tab::make('MD / DMD Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestsStatus::MD_DMD_Approved))
                ->badge(PurchaseRequests::query()->where('status', PurchaseRequestsStatus::MD_DMD_Approved)->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle');

            $tabs['md_dmd_rejected'] = Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    PurchaseRequestsStatus::MD_DMD_Rejected,
                    PurchaseRequestsStatus::Canceled,
                ]))
                ->badge(PurchaseRequests::query()->whereIn('status', [
                    PurchaseRequestsStatus::MD_DMD_Rejected,
                    PurchaseRequestsStatus::Canceled,
                ])->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle');

            $tabs['md_dmd_completed'] = Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestsStatus::Closed))
                ->badge(PurchaseRequests::query()->where('status', PurchaseRequestsStatus::Closed)->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-badge');
        }

        // Fallback when user can view the list but no tab block matched above
        if ($tabs === [] && Auth::user()->can('view_any_purchase::requests')) {
            $tabs['all'] = Tab::make('All')
                ->badge(PurchaseRequests::query()->count())
                ->icon('heroicon-o-rectangle-stack');
        }

        return $tabs;
    }
}
