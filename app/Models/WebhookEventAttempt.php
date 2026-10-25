<?php

namespace App\Models;

use App\WebhookEventAttemptOutcome;
use Database\Factories\WebhookEventAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'webhook_event_id',
    'attempt_number',
    'outcome',
    'error_code',
    'started_at',
    'finished_at',
])]
class WebhookEventAttempt extends Model
{
    /** @use HasFactory<WebhookEventAttemptFactory> */
    use HasFactory;

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'outcome' => WebhookEventAttemptOutcome::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
