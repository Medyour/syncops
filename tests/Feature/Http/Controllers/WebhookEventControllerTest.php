<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Connector;
use App\Models\WebhookEvent;
use App\Models\WebhookEventAttempt;
use App\Models\WebhookEventStatusChange;
use App\WebhookEventStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WebhookEventControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_event_index_displays_events_in_stable_newest_first_order(): void
    {
        $this->travelTo('2026-10-25 18:00:00');
        $connector = Connector::factory()->create(['name' => 'Commerce Demo']);
        WebhookEvent::factory()->for($connector)->create([
            'event_type' => 'order.older',
            'received_at' => now()->subMinute(),
        ]);
        WebhookEvent::factory()->for($connector)->create([
            'event_type' => 'order.newer',
            'received_at' => now(),
        ]);

        $response = $this->get(route('events.index'));

        $response
            ->assertOk()
            ->assertViewIs('events.index')
            ->assertSeeInOrder(['order.newer', 'order.older'])
            ->assertSee('Commerce Demo');
    }

    public function test_event_index_filters_by_status_connector_and_search_term(): void
    {
        $billing = Connector::factory()->create([
            'slug' => 'billing-demo',
            'name' => 'Billing Demo',
        ]);
        $commerce = Connector::factory()->create([
            'slug' => 'commerce-demo',
            'name' => 'Commerce Demo',
        ]);
        WebhookEvent::factory()->for($billing)->succeeded()->create([
            'source_event_id' => 'billing-match-1001',
            'event_type' => 'invoice.paid',
        ]);
        WebhookEvent::factory()->for($billing)->deadLettered()->create([
            'source_event_id' => 'billing-other-1002',
            'event_type' => 'invoice.failed',
        ]);
        WebhookEvent::factory()->for($commerce)->succeeded()->create([
            'source_event_id' => 'commerce-match-2001',
            'event_type' => 'order.paid',
        ]);

        $response = $this->get(route('events.index', [
            'status' => WebhookEventStatus::Succeeded->value,
            'connector' => $billing->slug,
            'q' => 'match-1001',
        ]));

        $response
            ->assertOk()
            ->assertSee('billing-match-1001')
            ->assertDontSee('billing-other-1002')
            ->assertDontSee('commerce-match-2001');
    }

    public function test_event_index_preserves_filters_across_pagination(): void
    {
        $connector = Connector::factory()->create([
            'slug' => 'billing-demo',
            'name' => 'Billing Demo',
        ]);
        WebhookEvent::factory()->for($connector)->succeeded()->count(16)->create();

        $response = $this->get(route('events.index', [
            'status' => WebhookEventStatus::Succeeded->value,
            'connector' => $connector->slug,
        ]));

        $response
            ->assertOk()
            ->assertViewHas('events', fn ($events): bool => $events->total() === 16
                && $events->count() === 15)
            ->assertSee('status=succeeded', false)
            ->assertSee('connector=billing-demo', false);
    }

    public function test_event_index_rejects_invalid_filters_with_422(): void
    {
        $response = $this->getJson(route('events.index', [
            'status' => 'unknown',
            'connector' => 'missing',
            'q' => str_repeat('x', 101),
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'connector', 'q']);
    }

    public function test_event_index_rejects_an_unexpected_filter_shape_with_422(): void
    {
        $response = $this->getJson(route('events.index', [
            'status' => [WebhookEventStatus::Succeeded->value],
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_search_term_is_bound_as_a_value(): void
    {
        $connector = Connector::factory()->create();
        WebhookEvent::factory()->for($connector)->create([
            'event_type' => 'invoice.paid',
        ]);

        $response = $this->get(route('events.index', [
            'q' => "' OR 1=1 --",
        ]));

        $response
            ->assertOk()
            ->assertDontSee('invoice.paid')
            ->assertSee('Aucun événement ne correspond à ces filtres.');
    }

    public function test_event_detail_displays_timeline_and_attempts_in_order(): void
    {
        $this->travelTo('2026-10-25 18:00:00');
        $connector = Connector::factory()->create([
            'slug' => 'commerce-demo',
            'name' => 'Commerce Demo',
        ]);
        $event = WebhookEvent::factory()->for($connector)->deadLettered()->create([
            'source_event_id' => 'commerce-event-2002',
            'event_type' => 'fulfillment.failed',
            'received_at' => now()->subMinutes(5),
        ]);
        WebhookEventStatusChange::factory()->for($event)->transition(
            WebhookEventStatus::Processing,
            WebhookEventStatus::RetryScheduled,
        )->create([
            'context' => ['reason_code' => 'first-timeline-step'],
            'recorded_at' => now()->subMinutes(3),
        ]);
        WebhookEventStatusChange::factory()->for($event)->transition(
            WebhookEventStatus::Processing,
            WebhookEventStatus::DeadLettered,
        )->create([
            'context' => ['reason_code' => 'second-timeline-step'],
            'recorded_at' => now()->subMinute(),
        ]);
        WebhookEventAttempt::factory()->for($event)->permanentFailure()->create([
            'attempt_number' => 2,
            'error_code' => 'second-attempt',
        ]);
        WebhookEventAttempt::factory()->for($event)->temporaryFailure()->create([
            'attempt_number' => 1,
            'error_code' => 'first-attempt',
        ]);

        $response = $this->get(route('events.show', $event));

        $response
            ->assertOk()
            ->assertViewIs('events.show')
            ->assertSee('fulfillment.failed')
            ->assertSee('commerce-event-2002')
            ->assertSeeInOrder(['first-timeline-step', 'second-timeline-step'])
            ->assertSeeInOrder(['first-attempt', 'second-attempt']);
    }

    public function test_event_detail_recursively_redacts_sensitive_payload_and_escapes_content(): void
    {
        $event = WebhookEvent::factory()->create([
            'payload' => [
                'API-Key' => 'api-secret-value',
                'profile' => [
                    'Email' => 'person@example.test',
                    'phoneNumber' => '+33 1 23 45 67 89',
                    'note' => "<script>alert('xss')</script>",
                ],
            ],
        ]);

        $response = $this->get(route('events.show', $event));

        $response
            ->assertOk()
            ->assertSee('[masqué]')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('api-secret-value')
            ->assertDontSee('person@example.test')
            ->assertDontSee('+33 1 23 45 67 89')
            ->assertDontSee("<script>alert('xss')</script>", false);
    }

    public function test_dead_lettered_event_detail_displays_csrf_protected_replay_form(): void
    {
        $event = WebhookEvent::factory()->deadLettered()->create();

        $response = $this->get(route('events.show', $event));

        $response
            ->assertOk()
            ->assertSee('Relancer l’événement')
            ->assertSee(route('events.replays.store', $event), false)
            ->assertSee('name="_token"', false);
    }

    public function test_succeeded_event_detail_does_not_display_replay_form(): void
    {
        $event = WebhookEvent::factory()->succeeded()->create();

        $response = $this->get(route('events.show', $event));

        $response
            ->assertOk()
            ->assertDontSee('Relancer l’événement');
    }

    public function test_unknown_event_detail_returns_404(): void
    {
        $response = $this->get(route('events.show', [
            'webhookEvent' => '01K8E9G7A63P43SHRZW38C6ZMQ',
        ]));

        $response->assertNotFound();
    }
}
