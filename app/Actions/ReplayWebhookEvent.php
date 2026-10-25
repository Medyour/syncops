<?php

namespace App\Actions;

use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\WebhookEventStatus;
use Illuminate\Support\Facades\DB;

class ReplayWebhookEvent
{
    /**
     * @return array{event: WebhookEvent, replay_number: int}|null
     */
    public function handle(WebhookEvent $event): ?array
    {
        return DB::transaction(function () use ($event): ?array {
            $lockedEvent = WebhookEvent::query()
                ->lockForUpdate()
                ->findOrFail($event->id);

            if ($lockedEvent->status !== WebhookEventStatus::DeadLettered) {
                return null;
            }

            $replayedAt = now();
            $replayNumber = $lockedEvent->statusChanges()
                ->where('to_status', WebhookEventStatus::Replayed->value)
                ->count() + 1;

            $lockedEvent->status = WebhookEventStatus::Queued;
            $lockedEvent->processed_at = null;
            $lockedEvent->save();
            $lockedEvent->statusChanges()->createMany([
                [
                    'from_status' => WebhookEventStatus::DeadLettered,
                    'to_status' => WebhookEventStatus::Replayed,
                    'context' => [
                        'replay_number' => $replayNumber,
                        'reason_code' => 'manual_demo_replay',
                    ],
                    'recorded_at' => $replayedAt,
                ],
                [
                    'from_status' => WebhookEventStatus::Replayed,
                    'to_status' => WebhookEventStatus::Queued,
                    'context' => [
                        'replay_number' => $replayNumber,
                        'source' => 'manual_replay',
                    ],
                    'recorded_at' => $replayedAt,
                ],
            ]);

            ProcessWebhookEvent::dispatch(
                $lockedEvent->id,
                $replayNumber,
            )->afterCommit();

            return [
                'event' => $lockedEvent,
                'replay_number' => $replayNumber,
            ];
        });
    }
}
