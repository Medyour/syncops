<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class AppSpecTest extends TestCase
{
    public function test_spec_defines_the_expected_london_topology_without_autodeploy(): void
    {
        $spec = $this->spec();

        $this->assertSame('syncops', $spec['name']);
        $this->assertSame('lon', $spec['region']);
        $this->assertSame([
            ['domain' => 'syncops.mkirchel.com', 'type' => 'PRIMARY'],
        ], $spec['domains']);
        $this->assertArrayNotHasKey('zone', $spec['domains'][0]);
        $this->assertArrayNotHasKey('vpc', $spec);
        $this->assertCount(1, $spec['services']);
        $this->assertCount(1, $spec['workers']);
        $this->assertCount(2, $spec['jobs']);
        $this->assertSame([
            ['name' => 'syncops-db', 'engine' => 'PG', 'version' => '18', 'production' => false],
        ], $spec['databases']);

        foreach ([$spec['services'][0], $spec['workers'][0], ...$spec['jobs']] as $component) {
            $this->assertSame([
                'repo' => 'Medyour/syncops',
                'branch' => 'main',
                'deploy_on_push' => false,
            ], $component['github']);
            $this->assertSame('.do/Dockerfile', $component['dockerfile_path']);
            $this->assertSame('apps-s-1vcpu-0.5gb', $component['instance_size_slug']);
            $this->assertSame(1, $component['instance_count']);
        }
    }

    public function test_web_worker_jobs_and_ingress_use_fail_closed_commands(): void
    {
        $spec = $this->spec();
        $web = $spec['services'][0];
        $worker = $spec['workers'][0];
        $jobs = array_column($spec['jobs'], null, 'name');

        $this->assertSame('sh .do/start-web.sh', $web['run_command']);
        $this->assertSame(8080, $web['http_port']);
        $this->assertSame('/health', $web['health_check']['http_path']);
        $this->assertSame('sh .do/start-worker.sh', $worker['run_command']);
        $this->assertSame('PRE_DEPLOY', $jobs['prepare-demo']['kind']);
        $this->assertSame('sh .do/pre-deploy.sh', $jobs['prepare-demo']['run_command']);
        $this->assertSame('SCHEDULED', $jobs['reset-demo']['kind']);
        $this->assertSame(['cron' => '0 4 * * *', 'time_zone' => 'Europe/Paris'], $jobs['reset-demo']['schedule']);
        $this->assertSame('sh .do/reset-demo.sh', $jobs['reset-demo']['run_command']);
        $this->assertSame([
            'rules' => [
                [
                    'match' => [
                        'authority' => ['exact' => 'syncops-znptj.ondigitalocean.app'],
                        'path' => ['prefix' => '/'],
                    ],
                    'redirect' => [
                        'authority' => 'syncops.mkirchel.com',
                        'scheme' => 'https',
                        'redirect_code' => 301,
                    ],
                ],
                [
                    'component' => ['name' => 'web'],
                    'match' => ['path' => ['prefix' => '/']],
                ],
            ],
        ], $spec['ingress']);
        $this->assertArrayNotHasKey('uri', $spec['ingress']['rules'][0]['redirect']);
    }

    public function test_runtime_variables_are_exact_bindings_without_committed_credentials(): void
    {
        $spec = $this->spec();
        $variables = array_column($spec['envs'], null, 'key');
        $expected = [
            'APP_NAME' => 'SyncOps',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'SYNCOPS_DEMO_MODE' => 'true',
            'APP_URL' => '${APP_URL}',
            'APP_LOCALE' => 'fr',
            'APP_FALLBACK_LOCALE' => 'fr',
            'DB_CONNECTION' => 'pgsql',
            'DATABASE_URL' => '${syncops-db.DATABASE_PRIVATE_URL}',
            'CACHE_STORE' => 'database',
            'SESSION_DRIVER' => 'database',
            'SESSION_ENCRYPT' => 'true',
            'SESSION_SECURE_COOKIE' => 'true',
            'SESSION_HTTP_ONLY' => 'true',
            'SESSION_SAME_SITE' => 'lax',
            'QUEUE_CONNECTION' => 'database',
            'DB_QUEUE' => 'webhooks',
            'DB_QUEUE_RETRY_AFTER' => '90',
            'LOG_CHANNEL' => 'stderr',
            'LOG_LEVEL' => 'warning',
            'MAIL_MAILER' => 'array',
        ];

        $this->assertSame(array_keys($expected), array_keys($variables));
        foreach ($expected as $key => $value) {
            $this->assertSame($value, $variables[$key]['value']);
            $this->assertSame('RUN_TIME', $variables[$key]['scope']);
        }
        $this->assertSame('SECRET', $variables['DATABASE_URL']['type']);
        $this->assertSame('GENERAL', $variables['APP_URL']['type']);

        $source = file_get_contents(base_path('.do/app.yaml'));
        $this->assertDoesNotMatchRegularExpression('/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i', $source);
        $this->assertDoesNotMatchRegularExpression('/(?:postgres(?:ql)?|mysql|mariadb|redis):\/\/[^$\s]+/i', $source);
        $this->assertDoesNotMatchRegularExpression('/(?:dop_v1_[A-Za-z0-9]{40,}|github_pat_[A-Za-z0-9_]{20,}|gh[oprsu]_[A-Za-z0-9]{20,}|-----BEGIN [A-Z ]*PRIVATE KEY-----)/', $source);
        $this->assertArrayNotHasKey('envs', $spec['services'][0]);
        $this->assertArrayNotHasKey('envs', $spec['workers'][0]);
    }

    public function test_scripts_and_image_refuse_unsafe_shortcuts(): void
    {
        foreach (['pre-deploy.sh', 'reset-demo.sh', 'start-web.sh', 'start-worker.sh'] as $script) {
            $source = file_get_contents(base_path('.do/'.$script));
            $this->assertStringContainsString('set -eu', $source);
            $this->assertStringNotContainsString('|| true', $source);
            $this->assertStringNotContainsString('migrate:fresh', $source);
            $this->assertStringNotContainsString('migrate:reset', $source);
            $this->assertStringNotContainsString('key:generate', $source);
        }

        $this->assertStringContainsString("production-check --database --no-interaction\nphp artisan migrate --force --no-interaction\nphp artisan syncops:demo-reset --force --exclusive --no-interaction", file_get_contents(base_path('.do/pre-deploy.sh')));
        $this->assertStringNotContainsString('migrate ', file_get_contents(base_path('.do/reset-demo.sh')));
        $this->assertStringContainsString('queue:work database --queue=webhooks', file_get_contents(base_path('.do/start-worker.sh')));

        $docker = file_get_contents(base_path('.do/Dockerfile'));
        preg_match_all('/^FROM (\S+)(?: AS \S+)?$/m', $docker, $images);
        foreach ($images[1] as $image) {
            if ($image !== 'application') {
                $this->assertMatchesRegularExpression('/@sha256:[a-f0-9]{64}$/', $image);
            }
        }
        $this->assertStringContainsString('docker-php-ext-install -j2 pdo_pgsql pdo_sqlite', $docker);
        $this->assertStringNotContainsString('ARG APP_KEY', $docker);
        $this->assertStringNotContainsString('ENV APP_KEY', $docker);
        $this->assertStringNotContainsString('ARG DATABASE_URL', $docker);
        $this->assertStringNotContainsString('ENV DATABASE_URL', $docker);
        $this->assertStringNotContainsString('config:cache', file_get_contents(base_path('.do/build-production.sh')));
        $this->assertStringNotContainsString('%U', file_get_contents(base_path('.do/apache.conf')));
    }

    /** @return array<string, mixed> */
    private function spec(): array
    {
        return Yaml::parseFile(base_path('.do/app.yaml'));
    }
}
