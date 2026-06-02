<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('reported_user_id');
            $table->string('reported_page_name_snapshot', 191);
            $table->string('reported_page_url_snapshot', 2048);

            $table->unsignedInteger('report_count')->default(1);
            $table->timestamp('first_reported_at');
            $table->timestamp('last_reported_at');

            $table->string('last_report_type', 120)->nullable();
            $table->text('last_report_message')->nullable();
            $table->unsignedBigInteger('last_reporter_user_id')->nullable();
            $table->string('last_reporter_ip_hash', 64)->nullable();

            $table->string('status', 20)->default('open');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by_user_id')->nullable();
            $table->string('moderator_comment', 40)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('reported_user_id', 'page_reports_reported_user_unique');
            $table->index(['status', 'report_count'], 'page_reports_status_count_idx');
            $table->index(['status', 'last_reported_at'], 'page_reports_status_last_idx');
            $table->index('last_reported_at', 'page_reports_last_reported_idx');
            $table->index('report_count', 'page_reports_count_idx');
            $table->index('last_reporter_user_id', 'page_reports_last_reporter_idx');
            $table->index('processed_by_user_id', 'page_reports_processed_by_idx');

            $table->foreign('last_reporter_user_id', 'page_reports_last_reporter_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('processed_by_user_id', 'page_reports_processed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            try {
                DB::statement(
                    "ALTER TABLE page_reports
                    ADD CONSTRAINT page_reports_report_count_chk
                    CHECK (report_count >= 1)"
                );
            } catch (\Throwable $e) {
                // Some database versions ignore or do not support CHECK constraints.
            }

            try {
                DB::statement(
                    "ALTER TABLE page_reports
                    ADD CONSTRAINT page_reports_status_chk
                    CHECK (status IN ('open','processed'))"
                );
            } catch (\Throwable $e) {
                // Some database versions ignore or do not support CHECK constraints.
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_reports');
    }
};
