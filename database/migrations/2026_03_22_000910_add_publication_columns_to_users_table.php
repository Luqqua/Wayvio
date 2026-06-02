<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'is_published')) {
                $table->boolean('is_published')->default(false)->after('block')->index();
            }

            if (!Schema::hasColumn('users', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('is_published');
            }
        });

        $this->backfillExistingActiveAccounts();
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['published_at', 'is_published'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillExistingActiveAccounts(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'is_published')) {
            return;
        }

        $query = DB::table('users')
            ->where('block', 'no');

        if (Schema::hasColumn('users', 'account_status')) {
            $query->where(function ($inner): void {
                $inner->whereNull('account_status')
                    ->orWhere('account_status', 'active');
            });
        }

        $query->update([
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
};

