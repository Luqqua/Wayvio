<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_report_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('page_report_id');
            $table->unsignedBigInteger('reporter_user_id')->nullable();
            $table->string('reporter_ip_hash', 64)->nullable();
            $table->string('report_type', 120)->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->foreign('page_report_id', 'page_report_events_report_fk')
                ->references('id')
                ->on('page_reports')
                ->cascadeOnDelete();

            $table->foreign('reporter_user_id', 'page_report_events_reporter_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['page_report_id', 'created_at'], 'page_report_events_report_created_idx');
            $table->index('reporter_user_id', 'page_report_events_reporter_idx');
            $table->index('reporter_ip_hash', 'page_report_events_ip_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_report_events');
    }
};
