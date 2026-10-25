<?php

namespace App;

enum WebhookProcessingOutcome
{
    case Succeeded;
    case TemporaryFailure;
    case PermanentFailure;
}
