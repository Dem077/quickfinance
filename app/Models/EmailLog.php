<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'status',
        'mailable',
        'subject',
        'body_html',
        'body_text',
        'recipients',
        'queue_connection',
        'queue_name',
        'queue_job_id',
        'error',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'pending' => $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENDING]),
            'sent', 'successful', 'success' => $query->where('status', self::STATUS_SENT),
            'failed' => $query->where('status', self::STATUS_FAILED),
            default => $query,
        };
    }

    public function mailableLabel(): string
    {
        if (! $this->mailable) {
            return 'Email';
        }

        return str(class_basename($this->mailable))->headline()->toString();
    }

    public function recipientsLabel(): string
    {
        $recipients = collect($this->recipients ?? [])
            ->filter()
            ->values()
            ->all();

        return $recipients === [] ? '—' : implode(', ', $recipients);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_SENDING], true);
    }
}
