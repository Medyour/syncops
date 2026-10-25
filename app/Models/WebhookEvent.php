<?php

namespace App\Models;

use App\WebhookEventStatus;
use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'connector_id',
    'source_event_id',
    'event_type',
    'status',
    'payload',
    'received_at',
    'processed_at',
])]
#[Hidden(['payload'])]
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory, HasUlids;

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(WebhookEventStatusChange::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(WebhookEventAttempt::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WebhookEventStatus::class,
            'payload' => 'array',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
