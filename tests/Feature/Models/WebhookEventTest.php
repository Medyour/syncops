<?php

namespace Tests\Feature\Models;

use App\Models\Connector;
use App\Models\WebhookEvent;
use App\Models\WebhookEventStatusChange;
use App\WebhookEventStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WebhookEventTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_event_exposes_typed_state_and_relationships_without_serializing_payload(): void
    {
        $connector = Connector::factory()->create();
        $event = WebhookEvent::factory()
            ->for($connector)
            ->create([
                'payload' => ['object_id' => 'object_demo_123'],
            ]);
        $statusChange = WebhookEventStatusChange::factory()
            ->for($event)
            ->transition(WebhookEventStatus::Received, WebhookEventStatus::Verified)
            ->create();

        $this->assertSame(WebhookEventStatus::Received, $event->status);
        $this->assertSame(['object_id' => 'object_demo_123'], $event->payload);
        $this->assertArrayNotHasKey('payload', $event->toArray());
        $this->assertTrue($event->connector->is($connector));
        $this->assertTrue($event->statusChanges->contains($statusChange));
    }

    public function test_same_source_event_id_is_allowed_for_different_connectors(): void
    {
        $sourceEventId = 'shared-source-event';

        $firstEvent = WebhookEvent::factory()->create([
            'source_event_id' => $sourceEventId,
        ]);
        $secondEvent = WebhookEvent::factory()
            ->for(Connector::factory())
            ->create([
                'source_event_id' => $sourceEventId,
            ]);

        $this->assertNotSame($firstEvent->connector_id, $secondEvent->connector_id);
        $this->assertSame($sourceEventId, $secondEvent->source_event_id);
    }

    public function test_same_source_event_id_is_rejected_for_one_connector(): void
    {
        $connector = Connector::factory()->create();
        WebhookEvent::factory()->for($connector)->create([
            'source_event_id' => 'duplicate-source-event',
        ]);

        $this->expectException(QueryException::class);

        WebhookEvent::factory()->for($connector)->create([
            'source_event_id' => 'duplicate-source-event',
        ]);
    }

    public function test_deleting_event_removes_its_status_history(): void
    {
        $event = WebhookEvent::factory()->create();
        $statusChange = WebhookEventStatusChange::factory()->for($event)->create();

        $event->delete();

        $this->assertModelMissing($statusChange);
    }
}
