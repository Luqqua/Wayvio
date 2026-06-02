<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('deleted_accounts')) {
            return;
        }

        $hasDeletionRequestId = Schema::hasColumn('deleted_accounts', 'deletion_request_id');
        $hasStripeCancelEventId = Schema::hasColumn('deleted_accounts', 'stripe_cancel_event_id');
        if (!$hasDeletionRequestId && !$hasStripeCancelEventId) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if ($hasDeletionRequestId) {
                $this->nullDuplicateColumnValues('deletion_request_id');
            }
            if ($hasStripeCancelEventId) {
                $this->nullDuplicateColumnValues('stripe_cancel_event_id');
            }
        }

        $addDeletionRequestUnique = $hasDeletionRequestId
            && !$this->indexExists('deleted_accounts', 'deleted_accounts_deletion_request_id_unique');
        $addStripeCancelEventUnique = $hasStripeCancelEventId
            && !$this->indexExists('deleted_accounts', 'deleted_accounts_stripe_cancel_event_id_unique');

        if (!$addDeletionRequestUnique && !$addStripeCancelEventUnique) {
            return;
        }

        Schema::table('deleted_accounts', function (Blueprint $table) use ($addDeletionRequestUnique, $addStripeCancelEventUnique): void {
            if ($addDeletionRequestUnique) {
                $table->unique('deletion_request_id', 'deleted_accounts_deletion_request_id_unique');
            }
            if ($addStripeCancelEventUnique) {
                $table->unique('stripe_cancel_event_id', 'deleted_accounts_stripe_cancel_event_id_unique');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('deleted_accounts')) {
            return;
        }

        $dropDeletionRequestUnique = $this->indexExists('deleted_accounts', 'deleted_accounts_deletion_request_id_unique');
        $dropStripeCancelEventUnique = $this->indexExists('deleted_accounts', 'deleted_accounts_stripe_cancel_event_id_unique');

        if (!$dropDeletionRequestUnique && !$dropStripeCancelEventUnique) {
            return;
        }

        Schema::table('deleted_accounts', function (Blueprint $table) use ($dropDeletionRequestUnique, $dropStripeCancelEventUnique): void {
            if ($dropDeletionRequestUnique) {
                $table->dropUnique('deleted_accounts_deletion_request_id_unique');
            }
            if ($dropStripeCancelEventUnique) {
                $table->dropUnique('deleted_accounts_stripe_cancel_event_id_unique');
            }
        });
    }

    private function nullDuplicateColumnValues(string $column): void
    {
        if (!in_array($column, ['deletion_request_id', 'stripe_cancel_event_id'], true)) {
            return;
        }

        DB::statement(
            "UPDATE deleted_accounts target
             JOIN (
               SELECT {$column} AS duplicate_value, MIN(id) AS keep_id
               FROM deleted_accounts
               WHERE {$column} IS NOT NULL AND {$column} <> ''
               GROUP BY {$column}
               HAVING COUNT(*) > 1
             ) duplicates ON duplicates.duplicate_value = target.{$column}
             SET target.{$column} = NULL
             WHERE target.id <> duplicates.keep_id"
        );
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $rows = DB::select(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [DB::getDatabaseName(), $table, $indexName],
            );

            return $rows !== [];
        }

        if ($driver === 'sqlite') {
            $rows = DB::select('PRAGMA index_list(' . DB::getPdo()->quote($table) . ')');
            foreach ($rows as $row) {
                if ((string) ($row->name ?? '') === $indexName) {
                    return true;
                }
            }
        }

        return false;
    }
};
