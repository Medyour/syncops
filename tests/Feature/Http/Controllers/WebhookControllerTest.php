<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Connector;
use App\Models\WebhookEvent;
use App\WebhookEventStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string SIGNING_KEY = 'syncops-test-signing-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'syncops.webhooks.max_payload_bytes' => 65536,
            'syncops.webhooks.rate_limit_per_minute' => 60,
            'syncops.connectors.billing-demo.secret' => self::SIGNING_KEY,
        ]);
    }

    public function test_valid_signed_request_creates_verified_event_and_returns_202(): void
    {
        $this->travelTo('2026-10-25 12:00:00');
        $connector = Connector::factory()->create(['slug' => 'billing-demo']);
        $payload = '{"invoice_id":"invoice_demo_4001","amount_minor":12900}';

        $response = $this->postWebhook($connector, $payload, 'billing-event-4001');

        $response
            ->assertAccepted()
            ->assertJsonPath('data.status', WebhookEventStatus::Verified->value)
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonCount(3, 'data');
        $this->assertTrue(Str::isUlid($response->json('data.id')));
        $this->assertDatabaseHas('webhook_events', [
            'connector_id' => $connector->id,
            'source_event_id' => 'billing-event-4001',
            'event_type' => 'invoice.paid',
            'status' => WebhookEventStatus::Verified->value,
            'received_at' => '2026-10-25 12:00:00',
        ]);

        $event = WebhookEvent::query()->sole();

        $this->assertSame(
            ['invoice_id' => 'invoice_demo_4001', 'amount_minor' => 12900],
            $event->payload,
        );
        $this->assertSame(
            [WebhookEventStatus::Received, WebhookEventStatus::Verified],
            $event->statusChanges()->oldest('id')->pluck('to_status')->all(),
        );
    }

    #[DataProvider('invalidSignatures')]
    public function test_invalid_signature_returns_401_and_stores_nothing(?string $signature): void
    {
        $connector = Connector::factory()->create(['slug' => 'billing-demo']);
        $payload = '{"invoice_id":"invoice_demo_4002"}';

        $response = $this->postWebhook(
            $connector,
            $payload,
            'billing-event-4002',
            signature: $signature,
        );

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => [
                    'code' => 'invalid_signature',
                    'message' => 'La signature du webhook est invalide.',
                ],
            ]);
        $this->assertDatabaseCount('webhook_events', 0);
        $this->assertDatabaseCount('webhook_event_status_changes', 0);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function invalidSignatures(): array
    {
        return [
            'missing signature' => [null],
            'malformed signature' => ['sha256=invalid'],
            'incorrect signature' => ['sha256='.str_repeat('0', 64)],
        ];
    }

    public function test_missing_event_headers_returns_422_and_stores_nothing(): void
    {
        $connector = Connector::factory()->create(['slug' => 'billing-demo']);
        $payload = '{"invoice_id":"invoice_demo_4003"}';

        $response = $this->postWebhook(
            $connector,
            $payload,
            eventId: null,
            eventType: null,
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_event_id', 'event_type'])
            ->assertJsonPath(
                'errors.source_event_id.0',
                'L’en-tête X-SyncOps-Event-ID est obligatoire.',
            )
            ->assertJsonPath(
                'errors.event_type.0',
                'L’en-tête X-SyncOps-Event-Type est obligatoire.',
            );
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_invalid_event_headers_returns_422_and_stores_nothing(): void
    {
        $connector = Connector::factory()->create(['slug' => 'billing-demo']);
        $payload = '{"invoice_id":"invoice_demo_4004"}';

        $response = $this->postWebhook(
            $connector,
            $payload,
            eventId: 'invalid event id',
            eventType: str_repeat('a', 256),
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_event_id', 'event_type'])
            ->assertJsonPath(
                'errors.source_event_id.0',
                'L’en-tête X-SyncOps-Event-ID contient des caractères non autorisés.',
            )
            ->assertJsonPath(
                'errors.event_type.0',
                'L’en-tête X-SyncOps-Event-Type ne peut pas dépasser 255 caractères.',
            );
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_non_json_request_returns_415_and_stores_nothing(): void
    {
        $connector = Connector::factory()->create(['slug' => 'billing-demo']);
        $payload = '{"invoice_id":"invoice_demo_4005"}';

        $response = $this->postWebhook(
            $connector,
            $payload,
            'billing-event-4005',
            contentType: 'text/plain',
        );

        $response
            ->assertUnsupportedMediaType()
            ->assertJsonPath('error.code', 'unsupported_media_type');
        $this->assertDatabaseCount('webhook_events', 0);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_json_object_returns_422_and_stores_nothing(string $payload): void
    {
        $connector = Connector::factory()->create(['slug' => 'billing-demo']);

        $response = $this->postWebhook($connector, $payload, 'billing-event-4006');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'invalid_payload');
        $this->assertDatabaseCount('webhook_events', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'malformed JSON' => ['{"invoice_id":'],
            'top-level list' => ['["invoice_demo_4006"]'],
            'top-level scalar' => ['"invoice_demo_4006"'],
        ];
    }

    public function test_oversized_payload_returns_413_and_stores_nothing(): void
    {
        config(['syncops.webhooks.max_payload_bytes' => 32]);
        $connector = Connector::factory()->create(['slug' => 'billing-demo']);
        $payload = '{"value":"'.str_repeat('x', 32).'"}';

        $response = $this->postWebhook($connector, $payload, 'billing-event-4007');

        $response
            ->assertStatus(Response::HTTP_REQUEST_ENTITY_TOO_LARGE)
            ->assertJsonPath('error.code', 'payload_too_large');
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_unknown_connector_returns_404_and_stores_nothing(): void
    {
        $payload = '{"invoice_id":"invoice_demo_4008"}';

        $response = $this->postWebhookToSlug('unknown-demo', $payload, 'event-4008');

        $response->assertNotFound();
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_inactive_connector_returns_404_and_stores_nothing(): void
    {
        $connector = Connector::factory()->create([
            'slug' => 'billing-demo',
            'is_active' => false,
        ]);
        $payload = '{"invoice_id":"invoice_demo_4009"}';

        $response = $this->postWebhook($connector, $payload, 'billing-event-4009');

        $response->assertNotFound();
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_missing_connector_secret_returns_503_and_stores_nothing(): void
    {
        config(['syncops.connectors.billing-demo.secret' => null]);
        $connector = Connector::factory()->create(['slug' => 'billing-demo']);
        $payload = '{"invoice_id":"invoice_demo_4010"}';

        $response = $this->postWebhook($connector, $payload, 'billing-event-4010');

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('error.code', 'connector_unavailable');
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_repeated_event_returns_200_without_second_effect(): void
    {
        $connector = Connector::factory()->create(['slug' => 'billing-demo']);
        $payload = '{"invoice_id":"invoice_demo_4011"}';
        $firstResponse = $this->postWebhook($connector, $payload, 'billing-event-4011');

        $response = $this->postWebhook($connector, $payload, 'billing-event-4011');

        $firstResponse->assertAccepted();
        $response
            ->assertOk()
            ->assertJsonPath('data.status', WebhookEventStatus::Duplicate->value)
            ->assertJsonPath('data.duplicate', true);
        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseCount('webhook_event_status_changes', 2);
        $this->assertSame($firstResponse->json('data.id'), $response->json('data.id'));
    }

    public function test_rate_limit_returns_429_before_third_event_is_stored(): void
    {
        config(['syncops.webhooks.rate_limit_per_minute' => 2]);
        $connector = Connector::factory()->create(['slug' => 'billing-demo']);
        $payload = '{"invoice_id":"invoice_demo_4012"}';
        $this->postWebhook($connector, $payload, 'billing-event-4012-a')->assertAccepted();
        $this->postWebhook($connector, $payload, 'billing-event-4012-b')->assertAccepted();

        $response = $this->postWebhook($connector, $payload, 'billing-event-4012-c');

        $response->assertTooManyRequests();
        $this->assertDatabaseCount('webhook_events', 2);
    }

    private function postWebhook(
        Connector $connector,
        string $payload,
        ?string $eventId = 'billing-event-default',
        string|false|null $signature = false,
        ?string $eventType = 'invoice.paid',
        string $contentType = 'application/json',
    ): TestResponse {
        return $this->postWebhookToSlug(
            $connector->slug,
            $payload,
            $eventId,
            $signature,
            $eventType,
            $contentType,
        );
    }

    private function postWebhookToSlug(
        string $connectorSlug,
        string $payload,
        ?string $eventId,
        string|false|null $signature = false,
        ?string $eventType = 'invoice.paid',
        string $contentType = 'application/json',
    ): TestResponse {
        $server = [
            'CONTENT_TYPE' => $contentType,
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($signature !== null) {
            $server['HTTP_X_SYNCOPS_SIGNATURE'] = $signature === false
                ? $this->signature($payload)
                : $signature;
        }

        if ($eventId !== null) {
            $server['HTTP_X_SYNCOPS_EVENT_ID'] = $eventId;
        }

        if ($eventType !== null) {
            $server['HTTP_X_SYNCOPS_EVENT_TYPE'] = $eventType;
        }

        return $this->call(
            'POST',
            route('webhooks.store', ['connector' => $connectorSlug]),
            server: $server,
            content: $payload,
        );
    }

    private function signature(string $payload): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, self::SIGNING_KEY);
    }
}
