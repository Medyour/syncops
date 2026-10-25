<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Connector;
use App\Models\WebhookEvent;
use App\Models\WebhookEventAttempt;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class QuarantinedWebhookEventControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_quarantine_displays_only_dead_lettered_events_in_stable_newest_first_order(): void
    {
        $this->travelTo('2026-10-25 18:00:00');
        $connector = Connector::factory()->create(['name' => 'Commerce Demo']);
        $older = WebhookEvent::factory()->for($connector)->deadLettered()->create([
            'event_type' => 'order.older_failure',
            'received_at' => now()->subMinute(),
        ]);
        $newer = WebhookEvent::factory()->for($connector)->deadLettered()->create([
            'event_type' => 'order.newer_failure',
            'received_at' => now(),
        ]);
        WebhookEventAttempt::factory()->for($newer)->count(2)->sequence(
            ['attempt_number' => 1],
            ['attempt_number' => 2],
        )->create();
        WebhookEvent::factory()->for($connector)->succeeded()->create([
            'event_type' => 'order.succeeded',
        ]);

        $response = $this->get(route('quarantine.index'));

        $response
            ->assertOk()
            ->assertViewIs('quarantine.index')
            ->assertViewHas('events', fn ($events): bool => $events->total() === 2
                && $events->first()->is($newer)
                && $events->last()->is($older)
                && $events->first()->attempts_count === 2)
            ->assertSeeInOrder(['order.newer_failure', 'order.older_failure'])
            ->assertDontSee('order.succeeded')
            ->assertDontSee('Relancer l’événement');
    }

    public function test_quarantine_displays_an_empty_state(): void
    {
        $response = $this->get(route('quarantine.index'));

        $response
            ->assertOk()
            ->assertSee('Aucun événement n’est actuellement en quarantaine.');
    }

    public function test_quarantine_paginates_results(): void
    {
        WebhookEvent::factory()->deadLettered()->count(16)->create();

        $response = $this->get(route('quarantine.index'));

        $response
            ->assertOk()
            ->assertViewHas('events', fn ($events): bool => $events->total() === 16
                && $events->count() === 15)
            ->assertSee('page=2', false);
    }

    public function test_quarantine_escapes_connector_content(): void
    {
        $connector = Connector::factory()->create([
            'name' => "Commerce <script>alert('xss')</script>",
        ]);
        WebhookEvent::factory()->for($connector)->deadLettered()->create();

        $response = $this->get(route('quarantine.index'));

        $response
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('xss')</script>", false);
    }
}
