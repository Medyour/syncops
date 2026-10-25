<?php

namespace App\Services;

use App\Models\WebhookEvent;
use App\WebhookProcessingOutcome;

class SimulatedWebhookProcessor
{
    public function process(
        WebhookEvent $event,
        int $attemptNumber,
    ): WebhookProcessingOutcome {
        $permanentFailureEventTypes = config(
            'syncops.processing.permanent_failure_event_types',
            [],
        );

        if (is_array($permanentFailureEventTypes)
            && in_array($event->event_type, $permanentFailureEventTypes, true)) {
            return WebhookProcessingOutcome::PermanentFailure;
        }

        $temporaryFailures = config(
            'syncops.processing.temporary_failures_before_success',
            [],
        );
        $failuresBeforeSuccess = is_array($temporaryFailures)
            ? max(0, (int) ($temporaryFailures[$event->event_type] ?? 0))
            : 0;

        if ($attemptNumber <= $failuresBeforeSuccess) {
            return WebhookProcessingOutcome::TemporaryFailure;
        }

        return WebhookProcessingOutcome::Succeeded;
    }
}
