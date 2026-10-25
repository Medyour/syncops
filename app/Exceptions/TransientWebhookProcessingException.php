<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class TransientWebhookProcessingException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly string $webhookEventId,
        public readonly int $attemptNumber,
    ) {
        parent::__construct('Le traitement simulé du webhook a temporairement échoué.');
    }

    /**
     * @return array{webhook_event_id: string, attempt_number: int}
     */
    public function context(): array
    {
        return [
            'webhook_event_id' => $this->webhookEventId,
            'attempt_number' => $this->attemptNumber,
        ];
    }
}
