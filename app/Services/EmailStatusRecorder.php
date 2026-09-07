<?php

namespace App\Services;

use App\Models\EmailLog;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class EmailStatusRecorder
{
    protected static ?string $currentQueueJobId = null;

    public function handleJobQueued(JobQueued $event): void
    {
        if (! $event->job instanceof SendQueuedMailable) {
            return;
        }

        $mailable = $event->job->mailable;
        [$bodyHtml, $bodyText] = $this->bodiesFromMailable($mailable);

        EmailLog::query()->create([
            'uuid' => (string) Str::uuid(),
            'status' => EmailLog::STATUS_PENDING,
            'mailable' => $mailable::class,
            'subject' => $this->subjectFromMailable($mailable),
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'recipients' => $this->recipientsFromMailable($mailable),
            'queue_connection' => $event->connectionName,
            'queue_name' => $event->queue,
            'queue_job_id' => $event->id !== null ? (string) $event->id : null,
            'queued_at' => now(),
        ]);
    }

    public function handleJobProcessing(JobProcessing $event): void
    {
        if (! $this->isMailJob($event->job)) {
            static::$currentQueueJobId = null;

            return;
        }

        static::$currentQueueJobId = (string) $event->job->getJobId();

        EmailLog::query()
            ->where('queue_job_id', static::$currentQueueJobId)
            ->whereIn('status', [EmailLog::STATUS_PENDING, EmailLog::STATUS_SENDING])
            ->update(['status' => EmailLog::STATUS_SENDING]);
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        if ($this->isMailJob($event->job)) {
            static::$currentQueueJobId = null;
        }
    }

    public function handleMessageSending(MessageSending $event): void
    {
        $log = $this->findLogForCurrentJob();
        [$bodyHtml, $bodyText] = $this->bodiesFromMessage($event->message);

        if (! $log) {
            $log = EmailLog::query()->create([
                'uuid' => (string) Str::uuid(),
                'status' => EmailLog::STATUS_SENDING,
                'mailable' => $this->mailableFromMessageData($event->data),
                'subject' => $event->message->getSubject(),
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'recipients' => $this->recipientsFromMessage($event->message),
                'queue_job_id' => static::$currentQueueJobId,
                'queued_at' => now(),
            ]);
        } else {
            $log->fill([
                'status' => EmailLog::STATUS_SENDING,
                'subject' => $event->message->getSubject() ?: $log->subject,
                'body_html' => $bodyHtml ?: $log->body_html,
                'body_text' => $bodyText ?: $log->body_text,
                'recipients' => $this->recipientsFromMessage($event->message) ?: $log->recipients,
            ])->save();
        }

        $headers = $event->message->getHeaders();
        if ($headers->has('X-Email-Log-Id')) {
            $headers->remove('X-Email-Log-Id');
        }
        $headers->addTextHeader('X-Email-Log-Id', $log->uuid);
    }

    public function handleMessageSent(MessageSent $event): void
    {
        $uuid = $event->message->getHeaders()->get('X-Email-Log-Id')?->getBodyAsString();
        [$bodyHtml, $bodyText] = $this->bodiesFromMessage($event->message);

        $log = $uuid
            ? EmailLog::query()->where('uuid', $uuid)->first()
            : $this->findLogForCurrentJob();

        if (! $log) {
            EmailLog::query()->create([
                'uuid' => $uuid ?: (string) Str::uuid(),
                'status' => EmailLog::STATUS_SENT,
                'mailable' => $this->mailableFromMessageData($event->data),
                'subject' => $event->message->getSubject(),
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'recipients' => $this->recipientsFromMessage($event->message),
                'queue_job_id' => static::$currentQueueJobId,
                'queued_at' => now(),
                'sent_at' => now(),
            ]);

            return;
        }

        $log->fill([
            'status' => EmailLog::STATUS_SENT,
            'subject' => $event->message->getSubject() ?: $log->subject,
            'body_html' => $bodyHtml ?: $log->body_html,
            'body_text' => $bodyText ?: $log->body_text,
            'recipients' => $this->recipientsFromMessage($event->message) ?: $log->recipients,
            'error' => null,
            'sent_at' => now(),
            'failed_at' => null,
        ])->save();
    }

    public function handleJobFailed(JobFailed $event): void
    {
        if (! $this->isMailJob($event->job)) {
            return;
        }

        $jobId = (string) $event->job->getJobId();
        $mailable = $this->mailableFromQueueJob($event->job);

        $log = EmailLog::query()
            ->where('queue_job_id', $jobId)
            ->latest('id')
            ->first();

        $payload = [
            'status' => EmailLog::STATUS_FAILED,
            'error' => Str::limit($event->exception->getMessage(), 2000),
            'failed_at' => now(),
            'mailable' => $mailable ?? ($log?->mailable),
            'recipients' => $log?->recipients ?? $this->recipientsFromQueueJob($event->job),
            'subject' => $log?->subject ?? $this->subjectFromQueueJob($event->job),
        ];

        if ($log) {
            $log->fill($payload)->save();
        } else {
            EmailLog::query()->create([
                'uuid' => (string) Str::uuid(),
                'queue_job_id' => $jobId,
                'queued_at' => now(),
                ...$payload,
            ]);
        }

        static::$currentQueueJobId = null;
    }

    protected function findLogForCurrentJob(): ?EmailLog
    {
        if (! static::$currentQueueJobId) {
            return null;
        }

        return EmailLog::query()
            ->where('queue_job_id', static::$currentQueueJobId)
            ->latest('id')
            ->first();
    }

    protected function isMailJob(QueueJob $job): bool
    {
        try {
            $payload = $job->payload();
            $commandName = $payload['data']['commandName'] ?? null;

            if ($commandName === SendQueuedMailable::class) {
                return true;
            }

            $command = unserialize($payload['data']['command'] ?? '');

            return $command instanceof SendQueuedMailable;
        } catch (Throwable) {
            return str_starts_with($job->resolveName(), 'App\\Mail\\');
        }
    }

    protected function mailableFromQueueJob(QueueJob $job): ?string
    {
        try {
            $command = unserialize($job->payload()['data']['command'] ?? '');

            if ($command instanceof SendQueuedMailable) {
                return $command->mailable::class;
            }
        } catch (Throwable) {
            //
        }

        $name = $job->resolveName();

        return str_starts_with($name, 'App\\Mail\\') ? $name : null;
    }

    /**
     * @return list<string>
     */
    protected function recipientsFromQueueJob(QueueJob $job): array
    {
        try {
            $command = unserialize($job->payload()['data']['command'] ?? '');

            if ($command instanceof SendQueuedMailable) {
                return $this->recipientsFromMailable($command->mailable);
            }
        } catch (Throwable) {
            //
        }

        return [];
    }

    protected function subjectFromQueueJob(QueueJob $job): ?string
    {
        try {
            $command = unserialize($job->payload()['data']['command'] ?? '');

            if ($command instanceof SendQueuedMailable) {
                return $this->subjectFromMailable($command->mailable);
            }
        } catch (Throwable) {
            //
        }

        return null;
    }

    protected function mailableFromMessageData(array $data): ?string
    {
        $mailable = $data['__laravel_mailable'] ?? null;

        return is_string($mailable) ? $mailable : null;
    }

    protected function subjectFromMailable(MailableContract $mailable): ?string
    {
        try {
            if (method_exists($mailable, 'envelope')) {
                return $mailable->envelope()->subject;
            }
        } catch (Throwable) {
            //
        }

        return class_basename($mailable);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function bodiesFromMailable(MailableContract $mailable): array
    {
        try {
            if (method_exists($mailable, 'render')) {
                $html = $mailable->render();

                return [$html !== '' ? $html : null, null];
            }
        } catch (Throwable) {
            // Rendering can fail before models are fully available; body is filled on send.
        }

        return [null, null];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function bodiesFromMessage(Email $message): array
    {
        $html = $message->getHtmlBody();
        $text = $message->getTextBody();

        if (is_resource($html)) {
            $html = stream_get_contents($html) ?: null;
        }

        if (is_resource($text)) {
            $text = stream_get_contents($text) ?: null;
        }

        return [
            is_string($html) && $html !== '' ? $html : null,
            is_string($text) && $text !== '' ? $text : null,
        ];
    }

    /**
     * @return list<string>
     */
    protected function recipientsFromMailable(MailableContract $mailable): array
    {
        $addresses = [];

        foreach (['to', 'cc', 'bcc'] as $property) {
            if (! property_exists($mailable, $property)) {
                continue;
            }

            foreach ((array) $mailable->{$property} as $recipient) {
                if (is_array($recipient) && isset($recipient['address'])) {
                    $addresses[] = (string) $recipient['address'];
                } elseif (is_string($recipient)) {
                    $addresses[] = $recipient;
                }
            }
        }

        return array_values(array_unique(array_filter($addresses)));
    }

    /**
     * @return list<string>
     */
    protected function recipientsFromMessage(Email $message): array
    {
        return collect([
            ...$message->getTo(),
            ...$message->getCc(),
            ...$message->getBcc(),
        ])
            ->map(fn (Address $address): string => $address->getAddress())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
