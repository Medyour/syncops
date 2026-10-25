<?php

namespace App\Actions\Demo;

use App\Models\Connector;
use App\Models\WebhookEvent;
use App\Models\WebhookEventAttempt;
use App\Models\WebhookEventStatusChange;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

class ProductionReadiness
{
    /** @var list<string> */
    private const DEMO_CONNECTORS = ['billing-demo', 'commerce-demo', 'crm-demo'];

    public function check(
        bool $web = false,
        bool $worker = false,
        bool $database = false,
        bool $ready = false,
    ): void {
        $this->assertConfiguration([
            'APP_ENV' => app()->isProduction(),
            'APP_DEBUG' => config('app.debug') === false,
            'APP_URL' => is_string(config('app.url')) && str_starts_with(config('app.url'), 'https://'),
            'SYNCOPS_DEMO_MODE' => config('syncops.demo_mode') === true,
            'DB_CONNECTION' => config('database.default') === 'pgsql',
            'DATABASE_URL' => $this->hasValidPostgresUrl(),
            'CACHE_STORE' => config('cache.default') === 'database',
            'SESSION_DRIVER' => config('session.driver') === 'database',
            'SESSION_ENCRYPT' => config('session.encrypt') === true,
            'SESSION_SECURE_COOKIE' => config('session.secure') === true,
            'SESSION_HTTP_ONLY' => config('session.http_only') === true,
            'SESSION_SAME_SITE' => config('session.same_site') === 'lax',
            'QUEUE_CONNECTION' => config('queue.default') === 'database',
            'DB_QUEUE' => config('queue.connections.database.queue') === 'webhooks',
            'DB_QUEUE_RETRY_AFTER' => config('queue.connections.database.retry_after') > 30,
            'LOG_CHANNEL' => config('logging.default') === 'stderr',
            'LOG_LEVEL' => config('logging.channels.stderr.level') === 'warning',
            'MAIL_MAILER' => config('mail.default') === 'array',
            'APP_LOCALE' => config('app.locale') === 'fr',
            'APP_FALLBACK_LOCALE' => config('app.fallback_locale') === 'fr',
        ]);

        if ($web) {
            $this->assertWebSecrets();
        }

        if ($worker) {
            $this->assertConfiguration([
                'WORKER_QUEUE' => config('queue.connections.database.queue') === 'webhooks',
            ]);
        }

        if ($database || $ready) {
            DB::connection()->getPdo();
            $this->assertDatabaseOwnership();
        }

        if ($ready) {
            $this->assertDemoReady();
        }
    }

    private function assertWebSecrets(): void
    {
        $key = config('app.key');
        $decodedKey = is_string($key) && str_starts_with($key, 'base64:')
            ? base64_decode(substr($key, 7), true)
            : $key;

        $this->assertConfiguration([
            'APP_KEY' => is_string($decodedKey) && Encrypter::supported($decodedKey, (string) config('app.cipher')),
            'SYNCOPS_BILLING_DEMO_SECRET' => $this->isStrongSecret(config('syncops.connectors.billing-demo.secret')),
            'SYNCOPS_COMMERCE_DEMO_SECRET' => $this->isStrongSecret(config('syncops.connectors.commerce-demo.secret')),
            'SYNCOPS_CRM_DEMO_SECRET' => $this->isStrongSecret(config('syncops.connectors.crm-demo.secret')),
        ]);
    }

    private function hasValidPostgresUrl(): bool
    {
        $url = config('database.connections.pgsql.url');

        if (! is_string($url) || $url === '') {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array($parts['scheme'] ?? null, ['postgres', 'postgresql'], true)
            && isset($parts['host'], $parts['user'], $parts['path'])
            && $parts['path'] !== '/';
    }

    private function assertDatabaseOwnership(): void
    {
        if (Schema::hasTable('users') && DB::table('users')->exists()) {
            throw new LogicException('Database safety check failed: USERS.');
        }

        if (! Schema::hasTable('connectors')) {
            return;
        }

        $unknownConnectors = Connector::query()
            ->whereNotIn('slug', self::DEMO_CONNECTORS)
            ->exists();

        if ($unknownConnectors) {
            throw new LogicException('Database safety check failed: CONNECTORS.');
        }
    }

    private function assertDemoReady(): void
    {
        foreach (['connectors', 'webhook_events', 'webhook_event_status_changes', 'webhook_event_attempts'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new LogicException('Database readiness check failed: TABLES.');
            }
        }

        $this->assertConfiguration([
            'DEMO_CONNECTORS' => Connector::query()->count() === 3
                && Connector::query()->orderBy('slug')->pluck('slug')->all() === self::DEMO_CONNECTORS,
            'DEMO_EVENTS' => WebhookEvent::query()->count() === 6,
            'DEMO_STATUS_CHANGES' => WebhookEventStatusChange::query()->count() === 31,
            'DEMO_ATTEMPTS' => WebhookEventAttempt::query()->count() === 6,
        ]);
    }

    /** @param array<string, bool> $checks */
    private function assertConfiguration(array $checks): void
    {
        foreach ($checks as $name => $valid) {
            if (! $valid) {
                throw new LogicException("Production readiness check failed: {$name}.");
            }
        }
    }

    private function isStrongSecret(mixed $secret): bool
    {
        return is_string($secret) && strlen($secret) >= 32;
    }
}
