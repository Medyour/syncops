<?php

namespace Tests\Feature\Seeders;

use App\Models\WebhookEvent;
use App\WebhookEventStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_default_seeder_creates_six_reproducible_scenarios_without_public_accounts(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('connectors', 3);
        $this->assertDatabaseHas('connectors', ['slug' => 'billing-demo']);
        $this->assertDatabaseHas('connectors', ['slug' => 'commerce-demo']);
        $this->assertDatabaseHas('connectors', ['slug' => 'crm-demo']);
        $this->assertDatabaseCount('webhook_events', 6);
        $this->assertDatabaseCount('webhook_event_status_changes', 31);
        $this->assertDatabaseCount('webhook_event_attempts', 6);
        $this->assertDatabaseCount('users', 0);

        foreach ([
            WebhookEventStatus::Succeeded,
            WebhookEventStatus::Duplicate,
            WebhookEventStatus::Rejected,
            WebhookEventStatus::RetryScheduled,
            WebhookEventStatus::DeadLettered,
        ] as $status) {
            $this->assertDatabaseHas('webhook_events', ['status' => $status->value]);
        }

        $this->assertDatabaseHas('webhook_event_status_changes', [
            'to_status' => WebhookEventStatus::Replayed->value,
        ]);
    }

    public function test_seeded_events_have_ulid_correlation_ids_and_complete_timelines(): void
    {
        $this->seed();

        $events = WebhookEvent::query()
            ->with([
                'attempts' => fn ($query) => $query->orderBy('attempt_number'),
                'statusChanges' => fn ($query) => $query
                    ->orderBy('recorded_at')
                    ->orderBy('id'),
            ])
            ->get();

        foreach ($events as $event) {
            $this->assertTrue(Str::isUlid($event->id));
            $this->assertNotEmpty($event->statusChanges);
            $this->assertSame(
                WebhookEventStatus::Received,
                $event->statusChanges->first()->to_status,
            );
            $this->assertSame(
                $event->status,
                $event->statusChanges->last()->to_status,
            );
            $this->assertSame(
                $event->statusChanges
                    ->where('to_status', WebhookEventStatus::Processing)
                    ->count(),
                $event->attempts->count(),
            );
        }
    }
}
