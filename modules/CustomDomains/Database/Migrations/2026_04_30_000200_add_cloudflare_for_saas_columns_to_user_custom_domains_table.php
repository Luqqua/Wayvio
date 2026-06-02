<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_custom_domains')) {
            return;
        }

        Schema::table('user_custom_domains', function (Blueprint $table): void {
            if (!Schema::hasColumn('user_custom_domains', 'cloudflare_hostname_id')) {
                $table->string('cloudflare_hostname_id', 128)->nullable()->after('ssl_status')->index();
            }

            if (!Schema::hasColumn('user_custom_domains', 'cloudflare_hostname_status')) {
                $table->string('cloudflare_hostname_status', 64)->nullable()->after('cloudflare_hostname_id')->index();
            }

            if (!Schema::hasColumn('user_custom_domains', 'cloudflare_ssl_status')) {
                $table->string('cloudflare_ssl_status', 64)->nullable()->after('cloudflare_hostname_status')->index();
            }

            if (!Schema::hasColumn('user_custom_domains', 'cloudflare_ownership_verification')) {
                $table->json('cloudflare_ownership_verification')->nullable()->after('cloudflare_ssl_status');
            }

            if (!Schema::hasColumn('user_custom_domains', 'cloudflare_ssl_validation_records')) {
                $table->json('cloudflare_ssl_validation_records')->nullable()->after('cloudflare_ownership_verification');
            }

            if (!Schema::hasColumn('user_custom_domains', 'cloudflare_verification_errors')) {
                $table->json('cloudflare_verification_errors')->nullable()->after('cloudflare_ssl_validation_records');
            }

            if (!Schema::hasColumn('user_custom_domains', 'cloudflare_last_synced_at')) {
                $table->timestamp('cloudflare_last_synced_at')->nullable()->after('cloudflare_verification_errors')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_custom_domains')) {
            return;
        }

        Schema::table('user_custom_domains', function (Blueprint $table): void {
            foreach ([
                'cloudflare_last_synced_at',
                'cloudflare_verification_errors',
                'cloudflare_ssl_validation_records',
                'cloudflare_ownership_verification',
                'cloudflare_ssl_status',
                'cloudflare_hostname_status',
                'cloudflare_hostname_id',
            ] as $column) {
                if (Schema::hasColumn('user_custom_domains', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
