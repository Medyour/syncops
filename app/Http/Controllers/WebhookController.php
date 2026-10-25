<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceiveWebhookRequest;
use App\Jobs\ProcessWebhookEvent;
use App\Models\Connector;
use App\Models\WebhookEvent;
use App\WebhookEventStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ReceiveWebhookRequest $request, Connector $connector): JsonResponse
    {
        if (! $request->isJson()) {
            return $this->errorResponse(
                'unsupported_media_type',
                'Le corps doit être envoyé en JSON.',
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            );
        }

        $rawPayload = $request->getContent();
        $configuredConnectors = config('syncops.connectors');
        $connectorConfiguration = is_array($configuredConnectors)
            ? ($configuredConnectors[$connector->slug] ?? null)
            : null;
        $secret = is_array($connectorConfiguration)
            ? ($connectorConfiguration['secret'] ?? null)
            : null;

        if (! is_string($secret) || $secret === '') {
            return $this->errorResponse(
                'connector_unavailable',
                'Le connecteur est temporairement indisponible.',
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        if (! $this->hasValidSignature(
            $rawPayload,
            $request->header('X-SyncOps-Signature'),
            $secret,
        )) {
            return $this->errorResponse(
                'invalid_signature',
                'La signature du webhook est invalide.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        try {
            $payload = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->invalidPayloadResponse();
        }

        if (! is_array($payload) || preg_match('/\A\s*\{/', $rawPayload) !== 1) {
            return $this->invalidPayloadResponse();
        }

        $validated = $request->safe()->only(['source_event_id', 'event_type']);

        [$event, $isDuplicate] = DB::transaction(function () use (
            $connector,
            $payload,
            $validated,
        ): array {
            $receivedAt = now();
            $event = WebhookEvent::query()->createOrFirst(
                [
                    'connector_id' => $connector->id,
                    'source_event_id' => $validated['source_event_id'],
                ],
                [
                    'event_type' => $validated['event_type'],
                    'status' => WebhookEventStatus::Queued,
                    'payload' => $payload,
                    'received_at' => $receivedAt,
                ],
            );

            if (! $event->wasRecentlyCreated) {
                return [$event, true];
            }

            $event->statusChanges()->createMany([
                [
                    'from_status' => null,
                    'to_status' => WebhookEventStatus::Received,
                    'recorded_at' => $receivedAt,
                ],
                [
                    'from_status' => WebhookEventStatus::Received,
                    'to_status' => WebhookEventStatus::Verified,
                    'recorded_at' => $receivedAt,
                ],
                [
                    'from_status' => WebhookEventStatus::Verified,
                    'to_status' => WebhookEventStatus::Queued,
                    'recorded_at' => $receivedAt,
                ],
            ]);

            ProcessWebhookEvent::dispatch($event->id)->afterCommit();

            return [$event, false];
        });

        return response()->json([
            'data' => [
                'id' => $event->id,
                'status' => $isDuplicate
                    ? WebhookEventStatus::Duplicate->value
                    : $event->status->value,
                'duplicate' => $isDuplicate,
            ],
        ], $isDuplicate ? Response::HTTP_OK : Response::HTTP_ACCEPTED);
    }

    private function hasValidSignature(
        string $payload,
        ?string $providedSignature,
        string $secret,
    ): bool {
        if (! is_string($providedSignature)
            || preg_match('/\Asha256=([a-f0-9]{64})\z/i', $providedSignature, $matches) !== 1) {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $payload, $secret),
            strtolower($matches[1]),
        );
    }

    private function invalidPayloadResponse(): JsonResponse
    {
        return $this->errorResponse(
            'invalid_payload',
            'La charge utile doit être un objet JSON valide.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
