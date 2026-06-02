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
            if (!Schema::hasColumn('user_subscriptions', 'hub_slots_included')) {
                $table->unsignedInteger('hub_slots_included')->nullable()->after('tier_id');
            }

            if (!Schema::hasColumn('user_subscriptions', 'hub_slots_addon')) {
                $table->unsignedInteger('hub_slots_addon')->default(0)->after('hub_slots_included');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_subscriptions')) {
            return;
        }

        Schema::table('user_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('user_subscriptions', 'hub_slots_addon')) {
                $table->dropColumn('hub_slots_addon');
            }
            if (Schema::hasColumn('user_subscriptions', 'hub_slots_included')) {
                $table->dropColumn('hub_slots_included');
            }
        });
    }
};

