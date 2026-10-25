<?php

namespace App\Jobs;

use App\Exceptions\TransientWebhookProcessingException;
use App\Models\WebhookEvent;
use App\Models\WebhookEventAttempt;
use App\Services\SimulatedWebhookProcessor;
use App\WebhookEventAttemptOutcome;
use App\WebhookEventStatus;
use App\WebhookProcessingOutcome;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessWebhookEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 300;

    public function __construct(public string $webhookEventId)
    {
        $this->onQueue('webhooks');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    public function uniqueId(): string
    {
        return $this->webhookEventId;
    }

    public function handle(SimulatedWebhookProcessor $processor): void
    {
        $temporaryFailure = DB::transaction(function () use (
            $processor,
        ): ?TransientWebhookProcessingException {
            $event = WebhookEvent::query()
                ->lockForUpdate()
                ->find($this->webhookEventId);

            if (! $event instanceof WebhookEvent
                || ! in_array($event->status, [
                    WebhookEventStatus::Queued,
                    WebhookEventStatus::RetryScheduled,
                ], true)) {
                return null;
            }

            $attemptNumber = ((int) $event->attempts()->max('attempt_number')) + 1;
            $startedAt = now();
            $attempt = $event->attempts()->create([
                'attempt_number' => $attemptNumber,
                'outcome' => WebhookEventAttemptOutcome::Processing,
                'started_at' => $startedAt,
            ]);

            $this->transition(
                $event,
                WebhookEventStatus::Processing,
                ['attempt_number' => $attemptNumber],
                $startedAt,
            );

            $outcome = $processor->process($event, $attemptNumber);
            $finishedAt = now();

            return match ($outcome) {
                WebhookProcessingOutcome::Succeeded => $this->recordSuccess(
                    $event,
                    $attempt,
                    $attemptNumber,
                    $finishedAt,
                ),
                WebhookProcessingOutcome::TemporaryFailure => $this->recordTemporaryFailure(
                    $event,
                    $attempt,
                    $attemptNumber,
                    $finishedAt,
                ),
                WebhookProcessingOutcome::PermanentFailure => $this->recordPermanentFailure(
                    $event,
                    $attempt,
                    $attemptNumber,
                    $finishedAt,
                ),
            };
        });

        if ($temporaryFailure instanceof TransientWebhookProcessingException) {
            throw $temporaryFailure;
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $event = WebhookEvent::query()
                ->lockForUpdate()
                ->find($this->webhookEventId);

            if (! $event instanceof WebhookEvent
                || in_array($event->status, [
                    WebhookEventStatus::Succeeded,
                    WebhookEventStatus::DeadLettered,
                ], true)) {
                return;
            }

            $attemptNumber = (int) $event->attempts()->max('attempt_number');
            $this->transition(
                $event,
                WebhookEventStatus::DeadLettered,
                [
                    'attempt_number' => $attemptNumber,
                    'reason_code' => $exception instanceof TransientWebhookProcessingException
                        ? 'retry_limit_reached'
                        : 'processing_failed',
                ],
                now(),
            );
        });
    }

    private function recordSuccess(
        WebhookEvent $event,
        WebhookEventAttempt $attempt,
        int $attemptNumber,
        DateTimeInterface $finishedAt,
    ): ?TransientWebhookProcessingException {
        $attempt->update([
            'outcome' => WebhookEventAttemptOutcome::Succeeded,
            'finished_at' => $finishedAt,
        ]);
        $event->processed_at = $finishedAt;
        $this->transition(
            $event,
            WebhookEventStatus::Succeeded,
            ['attempt_number' => $attemptNumber],
            $finishedAt,
        );

        return null;
    }

    private function recordTemporaryFailure(
        WebhookEvent $event,
        WebhookEventAttempt $attempt,
        int $attemptNumber,
        DateTimeInterface $finishedAt,
    ): ?TransientWebhookProcessingException {
        $attempt->update([
            'outcome' => WebhookEventAttemptOutcome::TemporaryFailure,
            'error_code' => 'simulated_temporary_failure',
            'finished_at' => $finishedAt,
        ]);

        if ($this->attempts() >= $this->tries) {
            $this->transition(
                $event,
                WebhookEventStatus::DeadLettered,
                [
                    'attempt_number' => $attemptNumber,
                    'reason_code' => 'retry_limit_reached',
                ],
                $finishedAt,
            );

            return null;
        }

        $this->transition(
            $event,
            WebhookEventStatus::RetryScheduled,
            [
                'attempt_number' => $attemptNumber,
                'reason_code' => 'simulated_temporary_failure',
                'retry_delay_seconds' => $this->retryDelay($attemptNumber),
            ],
            $finishedAt,
        );

        return new TransientWebhookProcessingException(
            $event->id,
            $attemptNumber,
        );
    }

    private function recordPermanentFailure(
        WebhookEvent $event,
        WebhookEventAttempt $attempt,
        int $attemptNumber,
        DateTimeInterface $finishedAt,
    ): ?TransientWebhookProcessingException {
        $attempt->update([
            'outcome' => WebhookEventAttemptOutcome::PermanentFailure,
            'error_code' => 'simulated_permanent_failure',
            'finished_at' => $finishedAt,
        ]);
        $this->transition(
            $event,
            WebhookEventStatus::DeadLettered,
            [
                'attempt_number' => $attemptNumber,
                'reason_code' => 'simulated_permanent_failure',
            ],
            $finishedAt,
        );

        return null;
    }

    /**
     * @param  array<string, int|string>  $context
     */
    private function transition(
        WebhookEvent $event,
        WebhookEventStatus $toStatus,
        array $context,
        DateTimeInterface $recordedAt,
    ): void {
        $fromStatus = $event->status;
        $event->status = $toStatus;
        $event->save();
        $event->statusChanges()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'context' => $context,
            'recorded_at' => $recordedAt,
        ]);
    }

    private function retryDelay(int $attemptNumber): int
    {
        $backoff = $this->backoff();

        return $backoff[min($attemptNumber - 1, count($backoff) - 1)];
    }
}
