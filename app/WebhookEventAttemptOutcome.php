<?php

namespace App;

enum WebhookEventAttemptOutcome: string
{
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case TemporaryFailure = 'temporary_failure';
    case PermanentFailure = 'permanent_failure';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'En traitement',
            self::Succeeded => 'Réussie',
            self::TemporaryFailure => 'Erreur temporaire',
            self::PermanentFailure => 'Erreur définitive',
        };
    }
}
