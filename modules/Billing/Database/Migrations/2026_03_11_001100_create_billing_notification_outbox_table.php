<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('billing_notification_outbox')) {
            return;
        }

        Schema::create('billing_notification_outbox', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('email', 320);
            $table->string('template_key', 80)->index();
            $table->string('dedupe_key', 191)->unique();
            $table->string('source_event_id', 191)->nullable()->index();
            $table->string('source_event_type', 191)->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_notification_outbox');
    }
};
