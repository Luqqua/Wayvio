<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('partner_audit_log')) {
            return;
        }

        Schema::create('partner_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_user_id')->nullable()->index();
            $table->string('action', 80)->index();
            $table->string('actor', 64)->default('system')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_audit_log');
    }
};
