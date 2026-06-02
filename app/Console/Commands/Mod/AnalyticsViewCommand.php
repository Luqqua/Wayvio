<?php

namespace App\Console\Commands\Mod;

use App\Models\User;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Analytics\AnalyticsTierResolver;
use Illuminate\Console\Command;

class AnalyticsViewCommand extends BaseModCommand
{
    protected $signature = 'analytics:view {user_id}';
    protected $description = 'Aggregierte Internal-API-Analytics eines Users anzeigen.';


    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $user = User::find($this->argument('user_id'));
        if (!$user) {
            $this->error('User nicht gefunden.');
            return Command::FAILURE;
        }

        $tierResolver = app(AnalyticsTierResolver::class);
        $client = app(AnalyticsClient::class);

        $tierLevel = $tierResolver->tierLevel($user);
        $aggregates = $client->fetchAggregates($user->id, '30d', [
            'tier_level' => $tierLevel,
            'actor_user_id' => (int) $user->id,
        ]);

        $summary = is_array($aggregates['summary'] ?? null) ? $aggregates['summary'] : [];
        $views = (int) ($summary['views'] ?? 0);
        $clicks = (int) ($summary['clicks'] ?? $summary['link_clicks'] ?? 0);

        $this->line("User #{$user->id} ({$user->name}) - Tier: {$tierLevel} - 30d Views: {$views} - 30d Clicks: {$clicks}");

        $topLinks = $aggregates['top_links'] ?? [];
        if (!is_array($topLinks) || $topLinks === []) {
            $this->info('Keine Top-Links in den letzten 30 Tagen.');
            return Command::SUCCESS;
        }

        foreach ($topLinks as $row) {
            $linkId = (int) (is_array($row) ? ($row['link_id'] ?? 0) : ($row->link_id ?? 0));
            $title = is_array($row) ? ($row['title'] ?? $row['name'] ?? 'Untitled link') : ($row->title ?? $row->name ?? 'Untitled link');
            $rowClicks = (int) (is_array($row) ? ($row['clicks'] ?? $row['count'] ?? 0) : ($row->clicks ?? $row->count ?? 0));
            $this->line(sprintf('#%s | %s | clicks=%s', $linkId, (string) $title, $rowClicks));
        }

        return Command::SUCCESS;
    }
}
