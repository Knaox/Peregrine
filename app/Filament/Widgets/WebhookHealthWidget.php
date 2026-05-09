<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\WebhookDelivery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Surface webhook delivery health to the admin dashboard. Three stats :
 *  - 24h success rate
 *  - failed deliveries pending retry
 *  - terminally expired deliveries (last 7 days)
 *
 * Hidden when no deliveries have ever been recorded — keeps the
 * dashboard clean on fresh installs without webhook traffic.
 */
class WebhookHealthWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Webhook health';

    public static function canView(): bool
    {
        return WebhookDelivery::count() > 0;
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $since24h = now()->subDay();

        $total24h = WebhookDelivery::where('last_attempted_at', '>=', $since24h)->count();
        $success24h = WebhookDelivery::where('last_attempted_at', '>=', $since24h)
            ->where('status', 'success')
            ->count();
        $rate = $total24h === 0 ? null : round(($success24h / $total24h) * 100, 1);

        $pendingRetries = WebhookDelivery::where('status', 'failed')
            ->whereNotNull('next_retry_at')
            ->count();

        $expired7d = WebhookDelivery::where('status', 'expired')
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        return [
            Stat::make('24h success rate', $rate === null ? '—' : $rate.' %')
                ->color($rate === null ? 'gray' : ($rate >= 99 ? 'success' : ($rate >= 90 ? 'warning' : 'danger'))),
            Stat::make('Pending retries', (string) $pendingRetries)
                ->color($pendingRetries === 0 ? 'success' : 'warning'),
            Stat::make('Expired (7d)', (string) $expired7d)
                ->color($expired7d === 0 ? 'success' : 'danger'),
        ];
    }
}
