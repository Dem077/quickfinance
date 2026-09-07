<?php

namespace App\Support;

use App\Enums\PurchaseRequestsStatus;
use App\Models\PurchaseRequests;
use App\Models\User;
use App\Support\PinnedTabs;
use Illuminate\Database\Eloquent\Builder;

class PurchaseRequestStatusTabs
{
    /**
     * Build permission-aware status tabs (mirrors Filament ListPurchaseRequests::getTabs).
     * Later role blocks overwrite earlier ones when a user holds multiple roles.
     *
     * @return array<int, array{key: string, label: string, badge: int, tone: string}>
     */
    public static function forUser(User $user): array
    {
        $tabs = [];

        if ($user->can('send_approval_purchase::requests')) {
            $tabs = [
                self::tab('all', 'All', self::requesterBase($user)->count(), 'neutral'),
                self::tab('draft', 'Draft', self::requesterBase($user)->where('status', PurchaseRequestsStatus::Draft)->count(), 'neutral'),
                self::tab(
                    'submitted',
                    'Awaiting Approval',
                    self::requesterBase($user)->whereIn('status', [
                        PurchaseRequestsStatus::Submitted,
                        PurchaseRequestsStatus::HODApproved,
                        PurchaseRequestsStatus::Approved,
                    ])->count(),
                    'warn',
                ),
                self::tab('procurement', 'Pending Procurement', self::requesterBase($user)->where('status', PurchaseRequestsStatus::MD_DMD_Approved)->count(), 'warn'),
                self::tab('completed', 'Completed', self::requesterBase($user)->where('status', PurchaseRequestsStatus::Closed)->count(), 'success'),
                self::tab(
                    'rejected',
                    'Rejected',
                    self::requesterBase($user)->whereIn('status', [
                        PurchaseRequestsStatus::HODRejected,
                        PurchaseRequestsStatus::Canceled,
                        PurchaseRequestsStatus::Rejected,
                        PurchaseRequestsStatus::MD_DMD_Rejected,
                    ])->count(),
                    'danger',
                ),
            ];
        }

        if ($user->hodof()->exists()) {
            $tabs = [
                self::tab('all', 'All', self::visibleBase($user)->count(), 'neutral'),
                self::tab('hodsubmitted', 'Awaiting Approval', self::visibleBase($user)->where('status', PurchaseRequestsStatus::Submitted)->count(), 'warn'),
                self::tab(
                    'hodrejected',
                    'Rejected',
                    self::visibleBase($user)->whereIn('status', [
                        PurchaseRequestsStatus::HODRejected,
                        PurchaseRequestsStatus::Canceled,
                    ])->count(),
                    'danger',
                ),
                self::tab(
                    'hodprocurement',
                    'Pending Procurement',
                    self::visibleBase($user)
                        ->where('status', PurchaseRequestsStatus::MD_DMD_Approved)
                        ->where('user_id', $user->id)
                        ->count(),
                    'warn',
                ),
                self::tab(
                    'hodcompleted',
                    'Completed',
                    self::visibleBase($user)
                        ->where('status', PurchaseRequestsStatus::Closed)
                        ->where('user_id', $user->id)
                        ->count(),
                    'success',
                ),
            ];
        }

        if ($user->can('approve_purchase::requests')) {
            $tabs = [
                self::tab('all', 'All', self::visibleBase($user)->count(), 'neutral'),
                self::tab('finance_approval', 'Pending Approval', self::visibleBase($user)->where('status', PurchaseRequestsStatus::HODApproved)->count(), 'warn'),
                self::tab('finance_rejected', 'Rejected', self::visibleBase($user)->where('status', PurchaseRequestsStatus::Canceled)->count(), 'danger'),
                self::tab('finance_approved', 'Approved', self::visibleBase($user)->where('status', PurchaseRequestsStatus::Approved)->count(), 'success'),
                self::tab('finance_completed', 'Completed', self::visibleBase($user)->where('status', PurchaseRequestsStatus::Closed)->count(), 'success'),
            ];
        }

        if ($user->can('md_dmd_approve_purchase::requests')) {
            $tabs = [
                self::tab('all', 'All', self::visibleBase($user)->count(), 'neutral'),
                self::tab('md_dmd_pending', 'Pending Approval', self::visibleBase($user)->where('status', PurchaseRequestsStatus::Approved)->count(), 'warn'),
                self::tab('md_dmd_approved', 'MD / DMD Approved', self::visibleBase($user)->where('status', PurchaseRequestsStatus::MD_DMD_Approved)->count(), 'success'),
                self::tab(
                    'md_dmd_rejected',
                    'Rejected',
                    self::visibleBase($user)->whereIn('status', [
                        PurchaseRequestsStatus::MD_DMD_Rejected,
                        PurchaseRequestsStatus::Canceled,
                    ])->count(),
                    'danger',
                ),
                self::tab('md_dmd_completed', 'Completed', self::visibleBase($user)->where('status', PurchaseRequestsStatus::Closed)->count(), 'success'),
            ];
        }

        if ($tabs === [] && $user->can('view_any_purchase::requests')) {
            $tabs = [
                self::tab('all', 'All', self::visibleBase($user)->count(), 'neutral'),
            ];
        }

        return array_values($tabs);
    }

    public static function apply(Builder $query, User $user, string $tab): Builder
    {
        return match ($tab) {
            'draft' => $query->where('status', PurchaseRequestsStatus::Draft)->where('user_id', $user->id),
            'submitted' => $query
                ->whereIn('status', [
                    PurchaseRequestsStatus::Submitted,
                    PurchaseRequestsStatus::HODApproved,
                    PurchaseRequestsStatus::Approved,
                ])
                ->where('user_id', $user->id),
            'procurement' => $query->where('status', PurchaseRequestsStatus::MD_DMD_Approved)->where('user_id', $user->id),
            'completed' => $query->where('status', PurchaseRequestsStatus::Closed)->where('user_id', $user->id),
            'rejected' => $query
                ->whereIn('status', [
                    PurchaseRequestsStatus::HODRejected,
                    PurchaseRequestsStatus::Canceled,
                    PurchaseRequestsStatus::Rejected,
                    PurchaseRequestsStatus::MD_DMD_Rejected,
                ])
                ->where('user_id', $user->id),

            'hodsubmitted' => $query->where('status', PurchaseRequestsStatus::Submitted),
            'hodrejected' => $query->whereIn('status', [
                PurchaseRequestsStatus::HODRejected,
                PurchaseRequestsStatus::Canceled,
            ]),
            'hodprocurement' => $query->where('status', PurchaseRequestsStatus::MD_DMD_Approved)->where('user_id', $user->id),
            'hodcompleted' => $query->where('status', PurchaseRequestsStatus::Closed)->where('user_id', $user->id),

            'finance_approval' => $query->where('status', PurchaseRequestsStatus::HODApproved),
            'finance_rejected' => $query->where('status', PurchaseRequestsStatus::Canceled),
            'finance_approved' => $query->where('status', PurchaseRequestsStatus::Approved),
            'finance_completed' => $query->where('status', PurchaseRequestsStatus::Closed),

            'md_dmd_pending' => $query->where('status', PurchaseRequestsStatus::Approved),
            'md_dmd_approved' => $query->where('status', PurchaseRequestsStatus::MD_DMD_Approved),
            'md_dmd_rejected' => $query->whereIn('status', [
                PurchaseRequestsStatus::MD_DMD_Rejected,
                PurchaseRequestsStatus::Canceled,
            ]),
            'md_dmd_completed' => $query->where('status', PurchaseRequestsStatus::Closed),

            'all' => self::applyAllScope($query, $user),
            default => $query,
        };
    }

    public static function resolveActiveTab(User $user, ?string $requested, ?string $preferred = null): string
    {
        $tabs = self::forUser($user);
        $keys = collect($tabs)->pluck('key')->all();

        return PinnedTabs::resolve($requested, $preferred, $keys, $keys[0] ?? 'all');
    }

    private static function applyAllScope(Builder $query, User $user): Builder
    {
        // Requester-only "All" is scoped to own PRs (Filament parity).
        if (
            $user->can('send_approval_purchase::requests')
            && ! $user->hodof()->exists()
            && ! $user->can('approve_purchase::requests')
            && ! $user->can('md_dmd_approve_purchase::requests')
            && ! $user->hasRole('super_admin')
        ) {
            return $query->where('user_id', $user->id);
        }

        return $query;
    }

    private static function requesterBase(User $user): Builder
    {
        return PurchaseRequests::query()->where('user_id', $user->id);
    }

    private static function visibleBase(User $user): Builder
    {
        return PurchaseRequests::query()->visibleTo($user);
    }

    /**
     * @return array{key: string, label: string, badge: int, tone: string}
     */
    private static function tab(string $key, string $label, int $badge, string $tone): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'badge' => $badge,
            'tone' => $tone,
        ];
    }
}
