<?php

namespace App\Queries;

use App\Models\WebhookEvent;
use App\WebhookEventStatus;

class DashboardSummary
{
    /**
     * @return array{
     *     total: int,
     *     succeeded: int,
     *     retry_scheduled: int,
     *     dead_lettered: int,
     *     success_rate: float
     * }
     */
    public function get(): array
    {
        $totals = WebhookEvent::query()
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS succeeded',
                [WebhookEventStatus::Succeeded->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS retry_scheduled',
                [WebhookEventStatus::RetryScheduled->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS dead_lettered',
                [WebhookEventStatus::DeadLettered->value],
            )
            ->first();

        $total = (int) ($totals->total ?? 0);
        $succeeded = (int) ($totals->succeeded ?? 0);

        return [
            'total' => $total,
            'succeeded' => $succeeded,
            'retry_scheduled' => (int) ($totals->retry_scheduled ?? 0),
            'dead_lettered' => (int) ($totals->dead_lettered ?? 0),
            'success_rate' => $total === 0 ? 0.0 : round(($succeeded / $total) * 100, 1),
        ];
    }
}
