<?php

namespace App\Http\Livewire;

use App\Http\Livewire;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\User;
use App\Models\Link;
use Modules\Tiers\Services\SubscriptionManager;
use Modules\Tiers\Models\UserSubscription;

class UserTable extends DataTableComponent
{
    protected $model = User::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
        $this->setDefaultSort('created_at', 'asc');
        $this->setPerPageAccepted([50, 100, 250, 500, 1000, -1]);
        $this->setColumnSelectEnabled();
    }

    public function builder(): Builder
    {
        return User::query()
            ->withoutAgencyHubAccounts()
            ->leftJoin('partner_accounts', 'partner_accounts.user_id', '=', 'users.id')
            ->select('users.*')
            ->selectRaw('partner_accounts.status as partner_status')
            ->selectRaw('partner_accounts.default_commission_rate_bps as partner_commission_rate_bps');
    }

    public function columns(): array
    {
        return [
            Column::make(__('messages.Status'), 'block')
                ->sortable()
                ->format(function ($value, $row) {
                    return $row->block === 'yes' ? __('messages.Pending') : __('messages.Approved');
                }),
            Column::make(__('messages.ID'), 'id')
                ->sortable(function (Builder $query, string $direction) {
                    $query->orderBy('users.id', $direction);
                })
                ->searchable(function (Builder $query, string $searchTerm) {
                    $query->orWhere('users.id', 'like', '%' . $searchTerm . '%');
                }),
            Column::make(__('messages.Name'), 'name')
                ->sortable()
                ->searchable(),
            Column::make(__('messages.Page'), 'littlelink_name')
                ->sortable()
                ->searchable()
                ->format(function ($value, $row, Column $column) {
                    if (!$row->littlelink_name == NULL) {
                        return "<a href='" . url('') . "/" . htmlspecialchars($row->littlelink_name) . "' target='_blank' class='text-info'><i class='bi bi-box-arrow-up-right'></i>&nbsp; " . htmlspecialchars($row->littlelink_name) . " </a>";
                    } else {
                        return 'N/A';
                    }
                })
                ->html(),
            Column::make(__('messages.Role'), 'role')
                ->sortable()
                ->searchable(),
            Column::make('Partner Status', 'id')
                ->format(function ($value, $row) {
                    $status = $row->partner_status ?? null;

                    if (!$status) {
                        return 'none';
                    }

                    return strtolower((string) $status);
                }),
            Column::make('Partner Provision', 'id')
                ->format(function ($value, $row) {
                    if ($row->partner_commission_rate_bps === null) {
                        return '-';
                    }

                    $bps = (int) $row->partner_commission_rate_bps;

                    return number_format($bps / 100, 2) . ' %';
                }),
            // MODULE: SaaS tier display (non-core addition)
            Column::make('Tier', 'id')
                ->format(function ($value, $row) {
                    $sm = app(SubscriptionManager::class);
                    $tier = $sm->getUserTier($row);
                    if (!$tier) {
                        return 'free';
                    }
                    return $tier->name ?? $tier->slug ?? 'free';
                }),
            Column::make('Links (Anzahl)', 'id')
                ->format(function ($value, $row) {
                    $linkCount = Link::where('user_id', $row->id)->count();
                    return $linkCount;
                }),
            Column::make('Subscribed at', 'id')
                ->format(function ($value, $row) {
                    $sub = UserSubscription::where('user_id', $row->id)->first();
                    return $sub?->created_at?->format('Y-m-d') ?? '-';
                }),
            Column::make('Expires at', 'id')
                ->format(function ($value, $row) {
                    $sub = UserSubscription::where('user_id', $row->id)->first();
                    return $sub?->expires_at ? \Illuminate\Support\Carbon::parse($sub->expires_at)->toDateString() : '-';
                }),
            Column::make('Verification', 'email_verified_at')
                ->sortable()
                ->format(function ($value, $row, Column $column) {
                    return $row->email_verified_at ? __('messages.Verified') : __('messages.Pending');
                }),
            Column::make(__('messages.Created at'), 'created_at')
                ->sortable(function (Builder $query, string $direction) {
                    $query->orderBy('users.created_at', $direction);
                })
                ->format(function ($value, $row) {
                    if ($row->created_at) {
                        return $row->created_at->format('d/m/y');
                    } else {
                        return '';
                    }
                }),
            Column::make(__('messages.Last seen'), 'updated_at')
                ->sortable(function (Builder $query, string $direction) {
                    $query->orderBy('users.updated_at', $direction);
                })
                ->format(function ($value, $row) {
                    $seenAt = $row->updated_at;
                    if (!$seenAt) {
                        return '';
                    }

                    $now = now();
                    $diff = $now->diff($seenAt);
            
                    if ($diff->d < 1 && $diff->h < 1) {
                        return 'Now';
                    } elseif ($diff->d < 1 && $diff->h < 24) {
                        return $diff->h . ' hours ago';
                    } elseif ($diff->d < 365) {
                        return $diff->d . ' days ago';
                    } else {
                        return $diff->y . ' years ago';
                    }
                }),
        ];
    }
}
