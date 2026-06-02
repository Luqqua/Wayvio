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
            if (!Schema::hasColumn('partner_accounts', 'legal_country')) {
                $table->string('legal_country', 2)->nullable()->after('stripe_connect_account_id');
                $table->index('legal_country');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('partner_accounts')) {
            return;
        }

        Schema::table('partner_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('partner_accounts', 'legal_country')) {
                $table->dropIndex(['legal_country']);
                $table->dropColumn('legal_country');
            }
        });
    }
};
