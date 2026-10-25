<?php

namespace App\Models;

use App\WebhookEventStatus;
use Database\Factories\WebhookEventStatusChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'webhook_event_id',
    'from_status',
    'to_status',
    'context',
    'recorded_at',
])]
class WebhookEventStatusChange extends Model
{
    /** @use HasFactory<WebhookEventStatusChangeFactory> */
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
            'from_status' => WebhookEventStatus::class,
            'to_status' => WebhookEventStatus::class,
            'context' => 'array',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
