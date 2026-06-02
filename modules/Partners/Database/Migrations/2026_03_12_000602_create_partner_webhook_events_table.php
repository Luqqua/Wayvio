<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('partner_webhook_events')) {
            return;
        }

        Schema::create('partner_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('external_event_id')->unique();
            $table->string('event_type', 120)->index();
            $table->char('payload_hash', 64)->nullable()->index();
            $table->json('payload')->nullable();
            $table->string('status', 80)->default('received')->index();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_webhook_events');
    }
};
