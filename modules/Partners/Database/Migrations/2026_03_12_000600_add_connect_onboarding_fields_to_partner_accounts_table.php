<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('partner_accounts')) {
            return;
        }

        Schema::table('partner_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_accounts', 'onboarding_started_at')) {
                $table->timestamp('onboarding_started_at')->nullable()->after('stripe_connect_account_id');
            }

            if (!Schema::hasColumn('partner_accounts', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('onboarding_started_at');
            }

            if (!Schema::hasColumn('partner_accounts', 'stripe_requirements')) {
                $table->json('stripe_requirements')->nullable()->after('activated_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('partner_accounts')) {
            return;
        }

        Schema::table('partner_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('partner_accounts', 'stripe_requirements')) {
                $table->dropColumn('stripe_requirements');
            }

            if (Schema::hasColumn('partner_accounts', 'activated_at')) {
                $table->dropColumn('activated_at');
            }

            if (Schema::hasColumn('partner_accounts', 'onboarding_started_at')) {
                $table->dropColumn('onboarding_started_at');
            }
        });
    }
};
