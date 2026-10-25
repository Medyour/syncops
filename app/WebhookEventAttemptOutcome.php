<?php

namespace App;

enum WebhookEventAttemptOutcome: string
{
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case TemporaryFailure = 'temporary_failure';
    case PermanentFailure = 'permanent_failure';
}
