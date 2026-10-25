<?php

namespace App\Actions\Demo;

use App\Models\Connector;
use App\Models\WebhookEvent;
use App\Models\WebhookEventAttempt;
use App\Models\WebhookEventStatusChange;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class ResetDemo
{
    /** @var list<string> */
    private const DEMO_CONNECTORS = ['billing-demo', 'commerce-demo', 'crm-demo'];

    /**
     * @return array{connectors: int, events: int, status_changes: int, attempts: int}
     */
    public function execute(bool $exclusive): array
    {
        return DB::transaction(function () use ($exclusive): array {
            $this->lockTables();

            if ($exclusive) {
                $this->assertExclusiveDemoDatabase();
            }

            foreach ([
                'failed_jobs',
                'job_batches',
                'jobs',
                'sessions',
                'cache_locks',
                'cache',
                'webhook_event_attempts',
                'webhook_event_status_changes',
                'webhook_events',
                'connectors',
            ] as $table) {
                DB::table($table)->delete();
            }

            app(DemoDataSeeder::class)->run();

            return [
                'connectors' => Connector::query()->count(),
                'events' => WebhookEvent::query()->count(),
                'status_changes' => WebhookEventStatusChange::query()->count(),
                'attempts' => WebhookEventAttempt::query()->count(),
            ];
        }, 3);
    }

    private function assertExclusiveDemoDatabase(): void
    {
        if (DB::table('users')->exists()) {
            throw new LogicException('Reset refused: user accounts exist.');
        }

        if (Connector::query()->whereNotIn('slug', self::DEMO_CONNECTORS)->exists()) {
            throw new LogicException('Reset refused: unknown connector data exists.');
        }
    }

    private function lockTables(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('LOCK TABLE users, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, connectors, webhook_events, webhook_event_status_changes, webhook_event_attempts IN ACCESS EXCLUSIVE MODE');
    }
}
