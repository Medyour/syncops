<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Connector;
use App\Models\WebhookEvent;
use App\WebhookEventStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_displays_zero_state_without_events(): void
    {
        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertViewIs('dashboard')
            ->assertSee('0,0 %')
            ->assertSee('Les premiers webhooks apparaîtront ici.');
    }

    public function test_dashboard_displays_aggregate_metrics_and_latest_events(): void
    {
        $this->travelTo('2026-10-25 18:00:00');
        $connector = Connector::factory()->create(['name' => 'Billing Demo']);
        WebhookEvent::factory()->for($connector)->succeeded()->create([
            'event_type' => 'invoice.paid',
            'received_at' => now()->subMinutes(3),
        ]);
        WebhookEvent::factory()->for($connector)->create([
            'event_type' => 'invoice.payment_delayed',
            'status' => WebhookEventStatus::RetryScheduled,
            'received_at' => now()->subMinutes(2),
        ]);
        WebhookEvent::factory()->for($connector)->deadLettered()->create([
            'event_type' => 'invoice.failed',
            'received_at' => now()->subMinute(),
        ]);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertViewHas('summary', [
                'total' => 3,
                'succeeded' => 1,
                'retry_scheduled' => 1,
                'dead_lettered' => 1,
                'success_rate' => 33.3,
            ])
            ->assertViewHas(
                'recentEvents',
                fn ($events): bool => $events->pluck('event_type')->all() === [
                    'invoice.failed',
                    'invoice.payment_delayed',
                    'invoice.paid',
                ],
            )
            ->assertSee('33,3 %')
            ->assertSee('En quarantaine');
    }

    public function test_dashboard_escapes_connector_content(): void
    {
        $connector = Connector::factory()->create([
            'name' => "Billing <script>alert('xss')</script>",
        ]);
        WebhookEvent::factory()->for($connector)->create();

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('xss')</script>", false);
    }
}
