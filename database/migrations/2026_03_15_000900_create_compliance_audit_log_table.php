<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('compliance_audit_log')) {
            return;
        }

        Schema::create('compliance_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('event_type', 80)->index();
            $table->string('status', 32)->default('success')->index();
            $table->string('source', 32)->default('web')->index();
            $table->string('ip_address', 64)->nullable()->index();
            $table->string('user_agent', 255)->nullable();
            $table->string('request_id', 64)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'created_at'], 'idx_compliance_user_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_audit_log');
    }
};
