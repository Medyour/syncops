<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use App\Models\WebhookEventStatusChange;
use App\WebhookEventStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEventStatusChange>
 */
class WebhookEventStatusChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_event_id' => WebhookEvent::factory(),
            'from_status' => null,
            'to_status' => WebhookEventStatus::Received,
            'context' => null,
            'recorded_at' => now(),
        ];
    }

    public function transition(WebhookEventStatus $from, WebhookEventStatus $to): static
    {
        return $this->state(fn (array $attributes): array => [
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }
}
