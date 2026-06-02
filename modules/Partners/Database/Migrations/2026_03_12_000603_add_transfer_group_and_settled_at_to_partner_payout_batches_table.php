<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('partner_payout_batches')) {
            return;
        }

        Schema::table('partner_payout_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_payout_batches', 'transfer_group')) {
                $table->string('transfer_group', 120)->nullable()->index()->after('stripe_transfer_id');
            }

            if (!Schema::hasColumn('partner_payout_batches', 'settled_at')) {
                $table->timestamp('settled_at')->nullable()->index()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('partner_payout_batches')) {
            return;
        }

        Schema::table('partner_payout_batches', function (Blueprint $table) {
            if (Schema::hasColumn('partner_payout_batches', 'settled_at')) {
                $table->dropColumn('settled_at');
            }

            if (Schema::hasColumn('partner_payout_batches', 'transfer_group')) {
                $table->dropColumn('transfer_group');
            }
        });
    }
};
