<?php

namespace Tests\Feature;

use App\Actions\Demo\ProductionReadiness;
use Illuminate\Support\Facades\Config;
use LogicException;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    public function test_web_component_accepts_the_complete_fail_closed_configuration(): void
    {
        $this->configureProduction();

        app(ProductionReadiness::class)->check(web: true);

        $this->addToAssertionCount(1);
    }

    public function test_web_component_refuses_a_missing_application_key(): void
    {
        $this->configureProduction();
        Config::set('app.key');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('APP_KEY');

        app(ProductionReadiness::class)->check(web: true);
    }

    public function test_web_component_refuses_a_short_connector_secret_without_echoing_it(): void
    {
        $this->configureProduction();
        Config::set('syncops.connectors.crm-demo.secret', 'visible-but-unsafe');

        try {
            app(ProductionReadiness::class)->check(web: true);
            $this->fail('The unsafe secret should have been rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('SYNCOPS_CRM_DEMO_SECRET', $exception->getMessage());
            $this->assertStringNotContainsString('visible-but-unsafe', $exception->getMessage());
        }
    }

    public function test_database_url_must_be_a_complete_postgresql_url(): void
    {
        $this->configureProduction();
        Config::set('database.connections.pgsql.url', 'not-a-connection-url');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DATABASE_URL');

        app(ProductionReadiness::class)->check();
    }

    private function configureProduction(): void
    {
        app()->instance('env', 'production');

        Config::set([
            'app.debug' => false,
            'app.url' => 'https://syncops.mkirchel.com',
            'app.locale' => 'fr',
            'app.fallback_locale' => 'fr',
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.cipher' => 'AES-256-CBC',
            'syncops.demo_mode' => true,
            'syncops.connectors.billing-demo.secret' => str_repeat('b', 32),
            'syncops.connectors.commerce-demo.secret' => str_repeat('c', 32),
            'syncops.connectors.crm-demo.secret' => str_repeat('r', 32),
            'database.default' => 'pgsql',
            'database.connections.pgsql.url' => sprintf(
                '%s://%s:%s@%s/%s',
                'postgresql',
                'syncops',
                'password',
                'private-db:25060',
                'syncops?sslmode=require',
            ),
            'cache.default' => 'database',
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'queue.default' => 'database',
            'queue.connections.database.queue' => 'webhooks',
            'queue.connections.database.retry_after' => 90,
            'logging.default' => 'stderr',
            'logging.channels.stderr.level' => 'warning',
            'mail.default' => 'array',
        ]);
    }
}
