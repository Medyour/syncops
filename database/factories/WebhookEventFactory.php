<?php

namespace Database\Factories;

use App\Models\Connector;
use App\Models\WebhookEvent;
use App\WebhookEventStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connector_id' => Connector::factory(),
            'source_event_id' => 'event_'.fake()->unique()->uuid(),
            'event_type' => 'demo.object.changed',
            'status' => WebhookEventStatus::Received,
            'payload' => [
                'object_id' => 'object_'.fake()->unique()->randomNumber(6),
            ],
            'received_at' => now(),
            'processed_at' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WebhookEventStatus::Succeeded,
            'processed_at' => now(),
        ]);
    }

    public function deadLettered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WebhookEventStatus::DeadLettered,
        ]);
    }
}
