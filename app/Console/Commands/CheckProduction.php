<?php

namespace App\Console\Commands;

use App\Actions\Demo\ProductionReadiness;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use LogicException;
use Throwable;

#[Signature('syncops:production-check {--web} {--worker} {--database} {--ready}')]
#[Description('Refuse to start when the public demo configuration is unsafe or incomplete')]
class CheckProduction extends Command
{
    public function handle(ProductionReadiness $readiness): int
    {
        try {
            $readiness->check(
                web: (bool) $this->option('web'),
                worker: (bool) $this->option('worker'),
                database: (bool) $this->option('database'),
                ready: (bool) $this->option('ready'),
            );
        } catch (LogicException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->error('Production readiness check failed without exposing connection details.');

            return self::FAILURE;
        }

        $this->components->info('Production readiness check passed.');

        return self::SUCCESS;
    }
}
