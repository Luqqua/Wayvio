<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('billing_webhook_events')) {
            Schema::create('billing_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('external_event_id')->unique();
                $table->string('event_type')->index();
                $table->char('payload_hash', 64)->nullable()->index();
                $table->json('payload')->nullable();
                $table->string('status')->default('received')->index();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('billing_records') && Schema::hasColumn('billing_records', 'stripe_payment_id')) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement(
                    'DELETE FROM billing_records
                     WHERE id NOT IN (
                         SELECT MIN(id) FROM billing_records GROUP BY stripe_payment_id
                     )'
                );
            } else {
                DB::statement(
                    'DELETE newer FROM billing_records newer
                     INNER JOIN billing_records older
                       ON newer.stripe_payment_id = older.stripe_payment_id
                      AND newer.id > older.id'
                );
            }

            if (!$this->hasIndex('billing_records', 'billing_records_stripe_payment_id_unique')) {
                Schema::table('billing_records', function (Blueprint $table) {
                    $table->unique('stripe_payment_id', 'billing_records_stripe_payment_id_unique');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('billing_records') && $this->hasIndex('billing_records', 'billing_records_stripe_payment_id_unique')) {
            Schema::table('billing_records', function (Blueprint $table) {
                $table->dropUnique('billing_records_stripe_payment_id_unique');
            });
        }

        if (Schema::hasTable('billing_webhook_events')) {
            Schema::drop('billing_webhook_events');
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
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

        $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);

        return !empty($result);
    }
};
