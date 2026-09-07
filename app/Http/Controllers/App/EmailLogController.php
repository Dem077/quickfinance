<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Support\PinnedTabs;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EmailLog::class);

        $search = $request->string('search')->trim()->toString();
        $allowedTabs = ['all', 'pending', 'sent', 'failed'];
        $pinnedTab = $request->user()->pinnedTab(PinnedTabs::EMAILS);
        $status = PinnedTabs::resolve(
            $request->string('status')->trim()->toString() ?: null,
            $pinnedTab,
            $allowedTabs,
        );

        $baseQuery = fn () => EmailLog::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('subject', 'like', "%{$search}%")
                        ->orWhere('mailable', 'like', "%{$search}%")
                        ->orWhere('error', 'like', "%{$search}%")
                        ->orWhere('recipients', 'like', "%{$search}%")
                        ->orWhere('body_text', 'like', "%{$search}%")
                        ->orWhere('body_html', 'like', "%{$search}%");
                });
            });

        $tabs = [
            [
                'key' => 'all',
                'label' => 'All',
                'badge' => $baseQuery()->count(),
                'tone' => 'neutral',
            ],
            [
                'key' => 'pending',
                'label' => 'Pending',
                'badge' => $baseQuery()->status('pending')->count(),
                'tone' => 'warn',
            ],
            [
                'key' => 'sent',
                'label' => 'Successful',
                'badge' => $baseQuery()->status('sent')->count(),
                'tone' => 'success',
            ],
            [
                'key' => 'failed',
                'label' => 'Failed',
                'badge' => $baseQuery()->status('failed')->count(),
                'tone' => 'danger',
            ],
        ];

        $emails = $baseQuery()
            ->when($status !== 'all', fn ($query) => $query->status($status))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (EmailLog $log): array => [
                'id' => $log->id,
                'uuid' => $log->uuid,
                'status' => $log->status,
                'status_label' => match ($log->status) {
                    EmailLog::STATUS_PENDING => 'Pending',
                    EmailLog::STATUS_SENDING => 'Sending',
                    EmailLog::STATUS_SENT => 'Successful',
                    EmailLog::STATUS_FAILED => 'Failed',
                    default => Str::headline($log->status),
                },
                'mailable' => $log->mailable,
                'mailable_label' => $log->mailableLabel(),
                'subject' => $log->subject ?: '—',
                'body_html' => $log->body_html,
                'body_text' => $log->body_text,
                'has_body' => filled($log->body_html) || filled($log->body_text),
                'recipients' => $log->recipients ?? [],
                'recipients_label' => $log->recipientsLabel(),
                'error' => $log->error,
                'queued_at' => optional($log->queued_at)->toIso8601String(),
                'sent_at' => optional($log->sent_at)->toIso8601String(),
                'failed_at' => optional($log->failed_at)->toIso8601String(),
                'created_at' => optional($log->created_at)->toIso8601String(),
            ]);

        return Inertia::render('Emails/Index', [
            'emails' => $emails,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'tabs' => $tabs,
            'pinnedTab' => $pinnedTab,
        ]);
    }
}
