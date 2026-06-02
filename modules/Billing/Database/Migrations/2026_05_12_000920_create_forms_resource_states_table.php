<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('forms_resource_states')) {
            return;
        }

        Schema::create('forms_resource_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('tenant_owner_user_id')->nullable()->index();
            $table->string('status', 32)->default('active')->index();
            $table->string('reason', 32)->nullable()->index();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('pending_deletion_at')->nullable();
            $table->timestamp('delete_after_at')->nullable()->index();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms_resource_states');
    }
};
