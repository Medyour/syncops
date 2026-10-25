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
}
