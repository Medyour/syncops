<?php

namespace App;

enum WebhookEventStatus: string
{
    case Received = 'received';
    case Verified = 'verified';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
    case RetryScheduled = 'retry_scheduled';
    case DeadLettered = 'dead_lettered';
    case Replayed = 'replayed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Reçu',
            self::Verified => 'Vérifié',
            self::Queued => 'En attente',
            self::Processing => 'En traitement',
            self::Succeeded => 'Réussi',
            self::Rejected => 'Rejeté',
            self::Duplicate => 'Doublon',
            self::RetryScheduled => 'Nouvelle tentative',
            self::DeadLettered => 'En quarantaine',
            self::Replayed => 'Relancé',
        };
    }
}
