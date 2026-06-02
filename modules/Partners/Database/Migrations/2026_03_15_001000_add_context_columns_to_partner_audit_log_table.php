<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('partner_audit_log')) {
            return;
        }

        Schema::table('partner_audit_log', function (Blueprint $table): void {
            if (!Schema::hasColumn('partner_audit_log', 'actor_user_id')) {
                $table->unsignedBigInteger('actor_user_id')->nullable()->after('partner_user_id')->index();
            }

            if (!Schema::hasColumn('partner_audit_log', 'status')) {
                $table->string('status', 32)->default('success')->after('actor')->index();
            }

            if (!Schema::hasColumn('partner_audit_log', 'source')) {
                $table->string('source', 64)->default('partners')->after('status')->index();
            }

            if (!Schema::hasColumn('partner_audit_log', 'request_id')) {
                $table->string('request_id', 64)->nullable()->after('source')->index();
            }

            if (!Schema::hasColumn('partner_audit_log', 'ip_address')) {
                $table->string('ip_address', 64)->nullable()->after('request_id')->index();
            }

            if (!Schema::hasColumn('partner_audit_log', 'user_agent')) {
                $table->string('user_agent', 255)->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('partner_audit_log')) {
            return;
        }

        Schema::table('partner_audit_log', function (Blueprint $table): void {
            if (Schema::hasColumn('partner_audit_log', 'user_agent')) {
                $table->dropColumn('user_agent');
            }

            if (Schema::hasColumn('partner_audit_log', 'ip_address')) {
                $table->dropColumn('ip_address');
            }

            if (Schema::hasColumn('partner_audit_log', 'request_id')) {
                $table->dropColumn('request_id');
            }

            if (Schema::hasColumn('partner_audit_log', 'source')) {
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('partner_audit_log', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('partner_audit_log', 'actor_user_id')) {
                $table->dropColumn('actor_user_id');
            }
        });
    }
};
