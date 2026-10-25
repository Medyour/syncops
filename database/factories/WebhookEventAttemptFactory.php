<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use App\Models\WebhookEventAttempt;
use App\WebhookEventAttemptOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEventAttempt>
 */
class WebhookEventAttemptFactory extends Factory
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
            'attempt_number' => 1,
            'outcome' => WebhookEventAttemptOutcome::Processing,
            'error_code' => null,
            'started_at' => now(),
            'finished_at' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'outcome' => WebhookEventAttemptOutcome::Succeeded,
            'finished_at' => now(),
        ]);
    }

    public function temporaryFailure(): static
    {
        return $this->state(fn (array $attributes): array => [
            'outcome' => WebhookEventAttemptOutcome::TemporaryFailure,
            'error_code' => 'simulated_temporary_failure',
            'finished_at' => now(),
        ]);
    }

    public function permanentFailure(): static
    {
        return $this->state(fn (array $attributes): array => [
            'outcome' => WebhookEventAttemptOutcome::PermanentFailure,
            'error_code' => 'simulated_permanent_failure',
            'finished_at' => now(),
        ]);
    }
}
