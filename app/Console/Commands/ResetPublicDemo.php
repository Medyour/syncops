<?php

namespace App\Console\Commands;

use App\Actions\Demo\ResetDemo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use LogicException;
use Throwable;

#[Signature('syncops:demo-reset {--force : Confirm a destructive demo reset} {--exclusive : Refuse unknown records before resetting}')]
#[Description('Restore the deterministic public demonstration without creating accounts or credentials')]
class ResetPublicDemo extends Command
{
    public function handle(ResetDemo $resetDemo): int
    {
        if (! config('syncops.demo_mode')) {
            $this->components->error('Demo reset is disabled.');

            return self::FAILURE;
        }

        if (app()->isProduction() && (! $this->option('force') || ! $this->option('exclusive'))) {
            $this->components->error('Production resets require both --force and --exclusive.');

            return self::FAILURE;
        }

        try {
            $summary = $resetDemo->execute(exclusive: (bool) $this->option('exclusive'));
        } catch (LogicException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->error('Demo reset failed without exposing database details.');

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Demo restored: %d connectors, %d events, %d timeline entries and %d attempts. No account or credential was created.',
            $summary['connectors'],
            $summary['events'],
            $summary['status_changes'],
            $summary['attempts'],
        ));

        return self::SUCCESS;
    }
}
