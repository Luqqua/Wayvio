<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_subscriptions')) {
            return;
        }

        Schema::table('user_subscriptions', function (Blueprint $table): void {
            if (!Schema::hasColumn('user_subscriptions', 'hub_inventory_over_quota_since')) {
                $table->timestamp('hub_inventory_over_quota_since')
                    ->nullable()
                    ->after('agency_over_quota_count')
                    ->index();
            }

            if (!Schema::hasColumn('user_subscriptions', 'hub_inventory_over_quota_count')) {
                $table->unsignedInteger('hub_inventory_over_quota_count')
                    ->nullable()
                    ->after('hub_inventory_over_quota_since');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_subscriptions')) {
            return;
        }

        Schema::table('user_subscriptions', function (Blueprint $table): void {
            foreach ([
                'hub_inventory_over_quota_count',
                'hub_inventory_over_quota_since',
            ] as $column) {
                if (Schema::hasColumn('user_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
