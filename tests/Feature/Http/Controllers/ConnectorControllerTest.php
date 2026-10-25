<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Connector;
use App\Models\WebhookEvent;
use App\WebhookEventStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConnectorControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_connector_index_displays_connectors_in_stable_name_order_with_aggregate_counts(): void
    {
        $zeta = Connector::factory()->create([
            'slug' => 'zeta-demo',
            'name' => 'Zeta Demo',
        ]);
        $alpha = Connector::factory()->create([
            'slug' => 'alpha-demo',
            'name' => 'Alpha Demo',
        ]);
        WebhookEvent::factory()->for($alpha)->succeeded()->create();
        WebhookEvent::factory()->for($alpha)->count(2)->create([
            'status' => WebhookEventStatus::RetryScheduled,
        ]);
        WebhookEvent::factory()->for($alpha)->deadLettered()->create();
        WebhookEvent::factory()->for($zeta)->create();

        $response = $this->get(route('connectors.index'));

        $response
            ->assertOk()
            ->assertViewIs('connectors.index')
            ->assertViewHas('connectors', function ($connectors) use ($alpha, $zeta): bool {
                $alphaSummary = $connectors->firstWhere('id', $alpha->id);

                return $connectors->pluck('id')->all() === [$alpha->id, $zeta->id]
                    && $alphaSummary->webhook_events_count === 4
                    && $alphaSummary->succeeded_events_count === 1
                    && $alphaSummary->retry_scheduled_events_count === 2
                    && $alphaSummary->quarantined_events_count === 1;
            })
            ->assertSeeInOrder(['Alpha Demo', 'Zeta Demo'])
            ->assertSee(route('events.index', ['connector' => 'alpha-demo']), false);
    }

    public function test_connector_index_displays_empty_and_inactive_states(): void
    {
        $emptyResponse = $this->get(route('connectors.index'));

        $emptyResponse
            ->assertOk()
            ->assertSee('Aucun connecteur n’est configuré.');

        Connector::factory()->create([
            'name' => 'Archive Demo',
            'is_active' => false,
        ]);

        $this->get(route('connectors.index'))
            ->assertOk()
            ->assertSee('Archive Demo')
            ->assertSee('Inactif');
    }

    public function test_connector_index_escapes_name_and_description(): void
    {
        Connector::factory()->create([
            'name' => "Commerce <script>alert('name')</script>",
            'description' => "Description <script>alert('description')</script>",
        ]);

        $response = $this->get(route('connectors.index'));

        $response
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('name')</script>", false)
            ->assertDontSee("<script>alert('description')</script>", false);
    }
}
