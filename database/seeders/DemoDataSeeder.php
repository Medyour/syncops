<?php

namespace Database\Seeders;

use App\Models\Connector;
use App\Models\WebhookEvent;
use App\WebhookEventAttemptOutcome;
use App\WebhookEventStatus;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->connectors() as $connectorData) {
            Connector::query()->updateOrCreate(
                ['slug' => $connectorData['slug']],
                $connectorData,
            );
        }

        $events = $this->events();

        foreach ($events as $eventIndex => $eventData) {
            $connector = Connector::query()
                ->where('slug', $eventData['connector_slug'])
                ->firstOrFail();
            $recordedAt = now()->subMinutes((count($events) - $eventIndex) * 5);
            $finalStatus = $eventData['history'][array_key_last($eventData['history'])];

            $event = WebhookEvent::query()->updateOrCreate(
                [
                    'connector_id' => $connector->id,
                    'source_event_id' => $eventData['source_event_id'],
                ],
                [
                    'event_type' => $eventData['event_type'],
                    'status' => $finalStatus,
                    'payload' => $eventData['payload'],
                    'received_at' => $recordedAt,
                    'processed_at' => $finalStatus === WebhookEventStatus::Succeeded
                        ? $recordedAt->copy()->addSeconds(count($eventData['history']))
                        : null,
                ],
            );

            $event->attempts()->delete();
            $event->statusChanges()->delete();

            foreach ($eventData['history'] as $statusIndex => $status) {
                $event->statusChanges()->create([
                    'from_status' => $eventData['history'][$statusIndex - 1] ?? null,
                    'to_status' => $status,
                    'context' => $this->timelineContext($status),
                    'recorded_at' => $recordedAt->copy()->addSeconds($statusIndex),
                ]);
            }

            $attemptNumber = 0;

            foreach ($eventData['history'] as $statusIndex => $status) {
                if ($status !== WebhookEventStatus::Processing) {
                    continue;
                }

                $attemptNumber++;
                $nextStatus = $eventData['history'][$statusIndex + 1] ?? null;
                $outcome = match ($nextStatus) {
                    WebhookEventStatus::Succeeded => WebhookEventAttemptOutcome::Succeeded,
                    WebhookEventStatus::RetryScheduled => WebhookEventAttemptOutcome::TemporaryFailure,
                    WebhookEventStatus::DeadLettered => WebhookEventAttemptOutcome::PermanentFailure,
                    default => WebhookEventAttemptOutcome::Processing,
                };

                $event->attempts()->create([
                    'attempt_number' => $attemptNumber,
                    'outcome' => $outcome,
                    'error_code' => match ($outcome) {
                        WebhookEventAttemptOutcome::TemporaryFailure => 'simulated_temporary_failure',
                        WebhookEventAttemptOutcome::PermanentFailure => 'simulated_permanent_failure',
                        default => null,
                    },
                    'started_at' => $recordedAt->copy()->addSeconds($statusIndex),
                    'finished_at' => $outcome === WebhookEventAttemptOutcome::Processing
                        ? null
                        : $recordedAt->copy()->addSeconds($statusIndex + 1),
                ]);
            }
        }
    }

    /**
     * @return list<array{slug: string, name: string, description: string, is_active: bool}>
     */
    private function connectors(): array
    {
        return [
            [
                'slug' => 'billing-demo',
                'name' => 'Billing Demo',
                'description' => 'Événements fictifs de facturation et de paiement.',
                'is_active' => true,
            ],
            [
                'slug' => 'commerce-demo',
                'name' => 'Commerce Demo',
                'description' => 'Événements fictifs de commande et de livraison.',
                'is_active' => true,
            ],
            [
                'slug' => 'crm-demo',
                'name' => 'CRM Demo',
                'description' => 'Événements fictifs de synchronisation de contacts.',
                'is_active' => true,
            ],
        ];
    }

    /**
     * @return list<array{
     *     connector_slug: string,
     *     source_event_id: string,
     *     event_type: string,
     *     payload: array<string, int|string>,
     *     history: list<WebhookEventStatus>
     * }>
     */
    private function events(): array
    {
        return [
            [
                'connector_slug' => 'billing-demo',
                'source_event_id' => 'billing-event-1001',
                'event_type' => 'invoice.paid',
                'payload' => [
                    'invoice_id' => 'invoice_demo_1001',
                    'amount_minor' => 12900,
                    'currency' => 'EUR',
                ],
                'history' => [
                    WebhookEventStatus::Received,
                    WebhookEventStatus::Verified,
                    WebhookEventStatus::Queued,
                    WebhookEventStatus::Processing,
                    WebhookEventStatus::Succeeded,
                ],
            ],
            [
                'connector_slug' => 'commerce-demo',
                'source_event_id' => 'commerce-event-2001',
                'event_type' => 'order.created',
                'payload' => [
                    'order_id' => 'order_demo_2001',
                    'total_minor' => 7490,
                    'currency' => 'EUR',
                ],
                'history' => [
                    WebhookEventStatus::Received,
                    WebhookEventStatus::Verified,
                    WebhookEventStatus::Duplicate,
                ],
            ],
            [
                'connector_slug' => 'crm-demo',
                'source_event_id' => 'crm-event-3001',
                'event_type' => 'contact.updated',
                'payload' => [
                    'contact_id' => 'contact_demo_3001',
                    'change' => 'company',
                ],
                'history' => [
                    WebhookEventStatus::Received,
                    WebhookEventStatus::Rejected,
                ],
            ],
            [
                'connector_slug' => 'billing-demo',
                'source_event_id' => 'billing-event-1002',
                'event_type' => 'invoice.payment_delayed',
                'payload' => [
                    'invoice_id' => 'invoice_demo_1002',
                    'amount_minor' => 5200,
                    'currency' => 'EUR',
                ],
                'history' => [
                    WebhookEventStatus::Received,
                    WebhookEventStatus::Verified,
                    WebhookEventStatus::Queued,
                    WebhookEventStatus::Processing,
                    WebhookEventStatus::RetryScheduled,
                ],
            ],
            [
                'connector_slug' => 'commerce-demo',
                'source_event_id' => 'commerce-event-2002',
                'event_type' => 'fulfillment.failed',
                'payload' => [
                    'fulfillment_id' => 'fulfillment_demo_2002',
                    'carrier' => 'demo-carrier',
                ],
                'history' => [
                    WebhookEventStatus::Received,
                    WebhookEventStatus::Verified,
                    WebhookEventStatus::Queued,
                    WebhookEventStatus::Processing,
                    WebhookEventStatus::RetryScheduled,
                    WebhookEventStatus::Processing,
                    WebhookEventStatus::DeadLettered,
                ],
            ],
            [
                'connector_slug' => 'crm-demo',
                'source_event_id' => 'crm-event-3002',
                'event_type' => 'contact.synced',
                'payload' => [
                    'contact_id' => 'contact_demo_3002',
                    'change' => 'lifecycle_stage',
                ],
                'history' => [
                    WebhookEventStatus::Received,
                    WebhookEventStatus::Verified,
                    WebhookEventStatus::Queued,
                    WebhookEventStatus::Processing,
                    WebhookEventStatus::Succeeded,
                    WebhookEventStatus::Replayed,
                ],
            ],
        ];
    }

    /**
     * @return array<string, int|string>|null
     */
    private function timelineContext(WebhookEventStatus $status): ?array
    {
        return match ($status) {
            WebhookEventStatus::Rejected => ['reason_code' => 'invalid_signature'],
            WebhookEventStatus::Duplicate => ['reason_code' => 'idempotency_key_seen'],
            WebhookEventStatus::RetryScheduled => [
                'reason_code' => 'simulated_timeout',
                'retry_number' => 1,
            ],
            WebhookEventStatus::DeadLettered => ['reason_code' => 'retry_limit_reached'],
            WebhookEventStatus::Replayed => ['reason_code' => 'manual_demo_replay'],
            default => null,
        };
    }
}
