<?php

namespace Tests\Feature;

use App\Models\Connector;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoResetCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reset_is_disabled_by_default(): void
    {
        $this->artisan('syncops:demo-reset')
            ->expectsOutputToContain('Demo reset is disabled.')
            ->assertFailed();
    }

    public function test_production_reset_requires_force_and_exclusive_options(): void
    {
        app()->instance('env', 'production');
        config()->set('syncops.demo_mode', true);

        $this->artisan('syncops:demo-reset', ['--force' => true])
            ->expectsOutputToContain('both --force and --exclusive')
            ->assertFailed();
    }

    public function test_exclusive_reset_refuses_user_accounts_and_preserves_existing_demo_data(): void
    {
        config()->set('syncops.demo_mode', true);
        $this->seed();
        User::factory()->create();

        $this->artisan('syncops:demo-reset', ['--exclusive' => true])
            ->expectsOutputToContain('user accounts exist')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('webhook_events', 6);
    }

    public function test_exclusive_reset_refuses_unknown_connectors(): void
    {
        config()->set('syncops.demo_mode', true);
        Connector::factory()->create(['slug' => 'customer-production']);

        $this->artisan('syncops:demo-reset', ['--exclusive' => true])
            ->expectsOutputToContain('unknown connector data exists')
            ->assertFailed();

        $this->assertDatabaseHas('connectors', ['slug' => 'customer-production']);
    }

    public function test_reset_clears_operational_state_and_restores_the_exact_demo_twice(): void
    {
        config()->set('syncops.demo_mode', true);
        $this->seed();
        DB::table('jobs')->insert([
            'queue' => 'webhooks',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
        DB::table('sessions')->insert([
            'id' => 'demo-session',
            'user_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        foreach ([1, 2] as $run) {
            $this->artisan('syncops:demo-reset', ['--exclusive' => true])
                ->expectsOutputToContain('3 connectors, 6 events, 31 timeline entries and 6 attempts')
                ->assertSuccessful();

            $this->assertDatabaseCount('connectors', 3);
            $this->assertDatabaseCount('webhook_events', 6);
            $this->assertDatabaseCount('webhook_event_status_changes', 31);
            $this->assertDatabaseCount('webhook_event_attempts', 6);
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('jobs', 0);
            $this->assertDatabaseCount('sessions', 0);
            $this->assertSame($run, $run);
        }
    }
}
