<?php

namespace App\Console\Commands\Mod;

use App\Models\AgencyHub;
use Illuminate\Console\Command;

class HubsListCommand extends BaseModCommand
{
    protected $signature = 'hubs:list {--agency_user_id=} {--status=} {--page=1}';

    protected $description = 'Alle Agency-Hubs anzeigen (inkl. Owner + Hub-Account), optional gefiltert.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $query = AgencyHub::query()
            ->with([
                'agencyUser:id,name,littlelink_name',
                'managedUser:id,name,littlelink_name',
            ])
            ->select('id', 'agency_user_id', 'managed_user_id', 'display_name', 'status');

        $agencyUserId = $this->option('agency_user_id');
        if ($agencyUserId !== null && $agencyUserId !== '') {
            if (!preg_match('/^\d+$/', (string) $agencyUserId)) {
                $this->error('agency_user_id muss eine positive Ganzzahl sein.');
                return Command::FAILURE;
            }
            $query->where('agency_user_id', (int) $agencyUserId);
        }

        $status = trim((string) ($this->option('status') ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $page = max(1, (int) $this->option('page'));
        $perPage = 50;

        $hubs = $query->orderBy('id')->forPage($page, $perPage)->get();
        if ($hubs->isEmpty()) {
            $this->info('Keine Hubs gefunden.');
            return Command::SUCCESS;
        }

        foreach ($hubs as $hub) {
            $ownerName = $hub->agencyUser?->name ?? '-';
            $ownerPage = $hub->agencyUser?->littlelink_name ?? '-';
            $managedName = $hub->managedUser?->name ?? '-';
            $managedPage = $hub->managedUser?->littlelink_name ?? '-';

            $this->line(sprintf(
                '#%d | owner=%d (%s / @%s) | hub=%d (%s / @%s) | display=%s | status=%s',
                (int) $hub->id,
                (int) $hub->agency_user_id,
                $ownerName,
                $ownerPage,
                (int) $hub->managed_user_id,
                $managedName,
                $managedPage,
                (string) $hub->display_name,
                (string) $hub->status,
            ));
        }

        $this->line(sprintf('Seite %d, max %d pro Seite.', $page, $perPage));

        return Command::SUCCESS;
    }
}

