<?php

namespace Tests\Feature\Http\Controllers;

use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\WebhookEventStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebhookEventReplayControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dead_lettered_event_is_requeued_and_returns_202(): void
    {
        $this->travelTo('2026-10-25 16:00:00');
        $event = WebhookEvent::factory()->deadLettered()->create([
            'processed_at' => now()->subMinute(),
        ]);
        Queue::fake([ProcessWebhookEvent::class]);

        $response = $this->postJson(route('events.replays.store', $event));

        $response
            ->assertAccepted()
            ->assertExactJson([
                'data' => [
                    'id' => $event->id,
                    'status' => WebhookEventStatus::Queued->value,
                    'replay_number' => 1,
                ],
            ]);
        $event->refresh();
        $this->assertSame(WebhookEventStatus::Queued, $event->status);
        $this->assertNull($event->processed_at);
        $statusChanges = $event->statusChanges()->oldest('id')->get();
        $this->assertSame(
            [WebhookEventStatus::Replayed, WebhookEventStatus::Queued],
            $statusChanges->pluck('to_status')->all(),
        );
        $this->assertSame([
            'replay_number' => 1,
            'reason_code' => 'manual_demo_replay',
        ], $statusChanges->first()->context);
        Queue::assertPushedOn(
            'webhooks',
            ProcessWebhookEvent::class,
            fn (ProcessWebhookEvent $job): bool => $job->webhookEventId === $event->id
                && $job->replayNumber === 1,
        );
    }

    #[DataProvider('nonReplayableStatuses')]
    public function test_non_quarantined_event_returns_409_without_side_effects(
        WebhookEventStatus $status,
    ): void {
        $event = WebhookEvent::factory()->create(['status' => $status]);
        Queue::fake([ProcessWebhookEvent::class]);

        $response = $this->postJson(route('events.replays.store', $event));

        $response
            ->assertConflict()
            ->assertExactJson([
                'error' => [
                    'code' => 'event_not_replayable',
                    'message' => 'Seuls les événements placés en quarantaine peuvent être relancés.',
                ],
            ]);
        $this->assertSame($status, $event->refresh()->status);
        $this->assertDatabaseCount('webhook_event_status_changes', 0);
        Queue::assertNothingPushed();
    }

    /**
     * @return array<string, array{WebhookEventStatus}>
     */
    public static function nonReplayableStatuses(): array
    {
        return [
            'received' => [WebhookEventStatus::Received],
            'verified' => [WebhookEventStatus::Verified],
            'queued' => [WebhookEventStatus::Queued],
            'processing' => [WebhookEventStatus::Processing],
            'succeeded' => [WebhookEventStatus::Succeeded],
            'rejected' => [WebhookEventStatus::Rejected],
            'duplicate' => [WebhookEventStatus::Duplicate],
            'retry scheduled' => [WebhookEventStatus::RetryScheduled],
            'replayed' => [WebhookEventStatus::Replayed],
        ];
    }

    public function test_repeated_replay_returns_409_and_queues_only_once(): void
    {
        $event = WebhookEvent::factory()->deadLettered()->create();
        Queue::fake([ProcessWebhookEvent::class]);
        $this->postJson(route('events.replays.store', $event))->assertAccepted();

        $response = $this->postJson(route('events.replays.store', $event));

        $response->assertConflict();
        $this->assertDatabaseCount('webhook_event_status_changes', 2);
        Queue::assertPushedTimes(ProcessWebhookEvent::class, 1);
    }

    public function test_unknown_event_returns_404_without_queuing_job(): void
    {
        Queue::fake([ProcessWebhookEvent::class]);

        $response = $this->postJson(route(
            'events.replays.store',
            ['webhookEvent' => '01K8E9G7A63P43SHRZW38C6ZMQ'],
        ));

        $response->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function test_rate_limit_returns_429_before_third_replay_is_queued(): void
    {
        config(['syncops.replays.rate_limit_per_minute' => 2]);
        $events = WebhookEvent::factory()->deadLettered()->count(3)->create();
        Queue::fake([ProcessWebhookEvent::class]);
        $this->postJson(route('events.replays.store', $events[0]))->assertAccepted();
        $this->postJson(route('events.replays.store', $events[1]))->assertAccepted();

        $response = $this->postJson(route('events.replays.store', $events[2]));

        $response->assertTooManyRequests();
        $this->assertSame(WebhookEventStatus::DeadLettered, $events[2]->refresh()->status);
        Queue::assertPushedTimes(ProcessWebhookEvent::class, 2);
    }
}
