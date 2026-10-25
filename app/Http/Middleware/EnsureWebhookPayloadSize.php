<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebhookPayloadSize
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maximumPayloadBytes = max(
            1,
            (int) config('syncops.webhooks.max_payload_bytes'),
        );
        $contentLength = $request->server('CONTENT_LENGTH');

        if ((is_numeric($contentLength) && (int) $contentLength > $maximumPayloadBytes)
            || strlen($request->getContent()) > $maximumPayloadBytes) {
            return response()->json([
                'error' => [
                    'code' => 'payload_too_large',
                    'message' => 'La charge utile dépasse la taille autorisée.',
                ],
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }
}
