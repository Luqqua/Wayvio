<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_run_log', function (Blueprint $table): void {
            $table->id();
            $table->string('job_name', 80)->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 16)->default('success')->index(); // success | failed | skipped
            $table->unsignedInteger('items_processed')->nullable();
            $table->unsignedInteger('items_affected')->nullable();
            $table->json('result_summary')->nullable();
            $table->string('error_message', 512)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['job_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_run_log');
    }
};
