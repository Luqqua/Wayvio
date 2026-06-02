<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const BILLING_WEBHOOK_EVENTS_CREATED_AT_INDEX = 'billing_webhook_events_created_at_idx';
    private const STRIPE_WEBHOOK_LOGS_CREATED_AT_INDEX = 'stripe_webhook_logs_created_at_idx';

    public function up(): void
    {
        if (
            Schema::hasTable('billing_webhook_events')
            && Schema::hasColumn('billing_webhook_events', 'created_at')
            && !$this->hasIndex('billing_webhook_events', self::BILLING_WEBHOOK_EVENTS_CREATED_AT_INDEX)
        ) {
            Schema::table('billing_webhook_events', function (Blueprint $table): void {
                $table->index('created_at', self::BILLING_WEBHOOK_EVENTS_CREATED_AT_INDEX);
            });
        }

        if (
            Schema::hasTable('stripe_webhook_logs')
            && Schema::hasColumn('stripe_webhook_logs', 'created_at')
            && !$this->hasIndex('stripe_webhook_logs', self::STRIPE_WEBHOOK_LOGS_CREATED_AT_INDEX)
        ) {
            Schema::table('stripe_webhook_logs', function (Blueprint $table): void {
                $table->index('created_at', self::STRIPE_WEBHOOK_LOGS_CREATED_AT_INDEX);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('billing_webhook_events', self::BILLING_WEBHOOK_EVENTS_CREATED_AT_INDEX)) {
            Schema::table('billing_webhook_events', function (Blueprint $table): void {
                $table->dropIndex(self::BILLING_WEBHOOK_EVENTS_CREATED_AT_INDEX);
            });
        }

        if ($this->hasIndex('stripe_webhook_logs', self::STRIPE_WEBHOOK_LOGS_CREATED_AT_INDEX)) {
            Schema::table('stripe_webhook_logs', function (Blueprint $table): void {
                $table->dropIndex(self::STRIPE_WEBHOOK_LOGS_CREATED_AT_INDEX);
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $entry) {
                if (($entry->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'pgsql') {
            $results = DB::select(
                'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
                [$table, $index]
            );

            return !empty($results);
        }

        $results = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);

        return !empty($results);
    }
};

