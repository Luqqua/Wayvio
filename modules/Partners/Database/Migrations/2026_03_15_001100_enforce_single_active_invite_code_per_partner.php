<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const MYSQL_UNIQUE_INDEX = 'partner_invite_codes_active_partner_unique';
    private const SQLITE_POSTGRES_UNIQUE_INDEX = 'partner_invite_codes_single_active_idx';

    public function up(): void
    {
        if (!Schema::hasTable('partner_invite_codes')) {
            return;
        }

        $this->deactivateDuplicateActiveCodes();

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $this->upMysql();

            return;
        }

        if ($driver === 'sqlite') {
            if (!$this->hasIndex('partner_invite_codes', self::SQLITE_POSTGRES_UNIQUE_INDEX)) {
                DB::statement(
                    'CREATE UNIQUE INDEX ' . self::SQLITE_POSTGRES_UNIQUE_INDEX
                    . " ON partner_invite_codes(partner_user_id) WHERE status = 'active'"
                );
            }

            return;
        }

        if ($driver === 'pgsql' && !$this->hasIndex('partner_invite_codes', self::SQLITE_POSTGRES_UNIQUE_INDEX)) {
            DB::statement(
                'CREATE UNIQUE INDEX ' . self::SQLITE_POSTGRES_UNIQUE_INDEX
                . " ON partner_invite_codes (partner_user_id) WHERE status = 'active'"
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('partner_invite_codes')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            if ($this->hasIndex('partner_invite_codes', self::MYSQL_UNIQUE_INDEX)) {
                DB::statement('DROP INDEX ' . self::MYSQL_UNIQUE_INDEX . ' ON partner_invite_codes');
            }

            return;
        }

        if ($this->hasIndex('partner_invite_codes', self::SQLITE_POSTGRES_UNIQUE_INDEX)) {
            DB::statement('DROP INDEX ' . self::SQLITE_POSTGRES_UNIQUE_INDEX);
        }
    }

    private function upMysql(): void
    {
        if (!$this->hasIndex('partner_invite_codes', self::MYSQL_UNIQUE_INDEX)) {
            DB::statement(
                'CREATE UNIQUE INDEX ' . self::MYSQL_UNIQUE_INDEX
                . " ON partner_invite_codes ((CASE WHEN status = 'active' THEN partner_user_id ELSE NULL END))"
            );
        }
    }

    private function deactivateDuplicateActiveCodes(): void
    {
        $duplicates = DB::table('partner_invite_codes')
            ->selectRaw('partner_user_id, MIN(id) AS keep_id, COUNT(*) AS duplicate_count')
            ->where('status', 'active')
            ->groupBy('partner_user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            $partnerUserId = (int) ($row->partner_user_id ?? 0);
            $keepId = (int) ($row->keep_id ?? 0);
            if ($partnerUserId <= 0 || $keepId <= 0) {
                continue;
            }

            DB::table('partner_invite_codes')
                ->where('partner_user_id', $partnerUserId)
                ->where('status', 'active')
                ->where('id', '!=', $keepId)
                ->update([
                    'status' => 'inactive',
                    'updated_at' => now(),
                ]);
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

        if ($driver === 'pgsql') {
            $results = DB::select(
                'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
                [$table, $index]
            );

            return !empty($results);
        }

        $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);

        return !empty($result);
    }
};
