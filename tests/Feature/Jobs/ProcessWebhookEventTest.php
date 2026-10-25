<?php

namespace Tests\Feature\Jobs;

use App\Exceptions\TransientWebhookProcessingException;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Models\WebhookEventAttempt;
use App\Services\SimulatedWebhookProcessor;
use App\WebhookEventAttemptOutcome;
use App\WebhookEventStatus;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ProcessWebhookEventTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_queued_event_is_processed_successfully(): void
    {
        $this->travelTo('2026-10-25 14:00:00');
        Http::preventStrayRequests();
        $event = WebhookEvent::factory()->create([
            'event_type' => 'invoice.paid',
            'status' => WebhookEventStatus::Queued,
        ]);

        (new ProcessWebhookEvent($event->id))->handle(new SimulatedWebhookProcessor);

        $event->refresh();

        $this->assertSame(WebhookEventStatus::Succeeded, $event->status);
        $this->assertSame('2026-10-25 14:00:00', $event->processed_at?->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('webhook_event_attempts', [
            'webhook_event_id' => $event->id,
            'attempt_number' => 1,
            'outcome' => WebhookEventAttemptOutcome::Succeeded->value,
            'error_code' => null,
        ]);
        $this->assertSame(
            [WebhookEventStatus::Processing, WebhookEventStatus::Succeeded],
            $event->statusChanges()->oldest('id')->pluck('to_status')->all(),
        );
    }

    public function test_temporary_failure_is_recorded_before_next_attempt_succeeds(): void
    {
        $this->travelTo('2026-10-25 14:10:00');
        config([
            'syncops.processing.temporary_failures_before_success' => [
                'invoice.payment_delayed' => 1,
            ],
        ]);
        $event = WebhookEvent::factory()->create([
            'event_type' => 'invoice.payment_delayed',
            'status' => WebhookEventStatus::Queued,
        ]);
        $job = new ProcessWebhookEvent($event->id);

        try {
            $job->handle(new SimulatedWebhookProcessor);
            $this->fail('La première tentative devait échouer temporairement.');
        } catch (TransientWebhookProcessingException $exception) {
            $this->assertSame($event->id, $exception->webhookEventId);
            $this->assertSame(1, $exception->attemptNumber);
        }

        $this->assertSame(WebhookEventStatus::RetryScheduled, $event->refresh()->status);
        $this->travel(5)->seconds();

        $job->handle(new SimulatedWebhookProcessor);

        $this->assertSame(WebhookEventStatus::Succeeded, $event->refresh()->status);
        $this->assertSame(
            [
                WebhookEventAttemptOutcome::TemporaryFailure,
                WebhookEventAttemptOutcome::Succeeded,
            ],
            $event->attempts()->oldest('attempt_number')->pluck('outcome')->all(),
        );
        $this->assertSame(
            [
                WebhookEventStatus::Processing,
                WebhookEventStatus::RetryScheduled,
                WebhookEventStatus::Processing,
                WebhookEventStatus::Succeeded,
            ],
            $event->statusChanges()->oldest('id')->pluck('to_status')->all(),
        );
        $this->assertStatusChangeContext(
            $event,
            WebhookEventStatus::RetryScheduled,
            [
                'attempt_number' => 1,
                'reason_code' => 'simulated_temporary_failure',
                'retry_delay_seconds' => 5,
            ],
        );
    }

    public function test_permanent_failure_is_dead_lettered_without_retry(): void
    {
        config([
            'syncops.processing.permanent_failure_event_types' => [
                'fulfillment.failed',
            ],
        ]);
        $event = WebhookEvent::factory()->create([
            'event_type' => 'fulfillment.failed',
            'status' => WebhookEventStatus::Queued,
        ]);

        (new ProcessWebhookEvent($event->id))->handle(new SimulatedWebhookProcessor);

        $this->assertSame(WebhookEventStatus::DeadLettered, $event->refresh()->status);
        $this->assertDatabaseHas('webhook_event_attempts', [
            'webhook_event_id' => $event->id,
            'attempt_number' => 1,
            'outcome' => WebhookEventAttemptOutcome::PermanentFailure->value,
            'error_code' => 'simulated_permanent_failure',
        ]);
        $this->assertDatabaseCount('webhook_event_attempts', 1);
    }

    public function test_replayed_recoverable_event_succeeds(): void
    {
        $event = WebhookEvent::factory()->create([
            'event_type' => 'contact.synced',
            'status' => WebhookEventStatus::Queued,
        ]);
        $event->statusChanges()->create([
            'from_status' => WebhookEventStatus::DeadLettered,
            'to_status' => WebhookEventStatus::Replayed,
            'context' => [
                'replay_number' => 1,
                'reason_code' => 'manual_demo_replay',
            ],
            'recorded_at' => now(),
        ]);

        (new ProcessWebhookEvent($event->id))->handle(new SimulatedWebhookProcessor);

        $this->assertSame(WebhookEventStatus::Succeeded, $event->refresh()->status);
        $this->assertDatabaseHas('webhook_event_attempts', [
            'webhook_event_id' => $event->id,
            'outcome' => WebhookEventAttemptOutcome::Succeeded->value,
        ]);
    }

    public function test_final_temporary_failure_is_dead_lettered_without_another_retry(): void
    {
        config([
            'syncops.processing.temporary_failures_before_success' => [
                'demo.always_unavailable' => 99,
            ],
        ]);
        $event = WebhookEvent::factory()->create([
            'event_type' => 'demo.always_unavailable',
            'status' => WebhookEventStatus::Queued,
        ]);
        WebhookEventAttempt::factory()
            ->count(2)
            ->sequence(
                ['attempt_number' => 1],
                ['attempt_number' => 2],
            )
            ->for($event)
            ->temporaryFailure()
            ->create();
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('attempts')->once()->andReturn(3);
        $job = (new ProcessWebhookEvent($event->id))->setJob($queueJob);

        $job->handle(new SimulatedWebhookProcessor);

        $this->assertSame(WebhookEventStatus::DeadLettered, $event->refresh()->status);
        $this->assertDatabaseCount('webhook_event_attempts', 3);
        $this->assertStatusChangeContext(
            $event,
            WebhookEventStatus::DeadLettered,
            [
                'attempt_number' => 3,
                'reason_code' => 'retry_limit_reached',
            ],
            WebhookEventStatus::Processing,
        );
    }

    public function test_unexpected_terminal_failure_is_recorded_by_failure_callback(): void
    {
        $event = WebhookEvent::factory()->create([
            'status' => WebhookEventStatus::Processing,
        ]);
        $exception = new \RuntimeException('Unexpected demo failure');

        (new ProcessWebhookEvent($event->id))->failed($exception);

        $this->assertSame(WebhookEventStatus::DeadLettered, $event->refresh()->status);
        $this->assertStatusChangeContext(
            $event,
            WebhookEventStatus::DeadLettered,
            [
                'attempt_number' => 0,
                'reason_code' => 'processing_failed',
            ],
            WebhookEventStatus::Processing,
        );
    }

    public function test_terminal_event_is_not_processed_again(): void
    {
        $event = WebhookEvent::factory()->succeeded()->create();

        (new ProcessWebhookEvent($event->id))->handle(new SimulatedWebhookProcessor);

        $this->assertDatabaseCount('webhook_event_attempts', 0);
        $this->assertDatabaseCount('webhook_event_status_changes', 0);
        $this->assertSame(WebhookEventStatus::Succeeded, $event->refresh()->status);
    }

    public function test_job_has_bounded_retry_and_uniqueness_policy(): void
    {
        $job = new ProcessWebhookEvent('01K8E9G7A63P43SHRZW38C6ZMQ');
        $replayJob = new ProcessWebhookEvent('01K8E9G7A63P43SHRZW38C6ZMQ', 2);

        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertSame(300, $job->uniqueFor);
        $this->assertSame([5, 30], $job->backoff());
        $this->assertSame('01K8E9G7A63P43SHRZW38C6ZMQ:replay:0', $job->uniqueId());
        $this->assertSame('01K8E9G7A63P43SHRZW38C6ZMQ:replay:2', $replayJob->uniqueId());
        $this->assertSame('webhooks', $job->queue);
    }

    /** @param array<string, int|string> $expectedContext */
    private function assertStatusChangeContext(
        WebhookEvent $event,
        WebhookEventStatus $toStatus,
        array $expectedContext,
        ?WebhookEventStatus $fromStatus = null,
    ): void {
        $query = $event->statusChanges()
            ->where('to_status', $toStatus->value);

        if ($fromStatus !== null) {
            $query->where('from_status', $fromStatus->value);
        }

        $this->assertSame($expectedContext, $query->firstOrFail()->context);
    }
}
