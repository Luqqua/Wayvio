<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('user_agreement_acceptances')) {
            return;
        }

        Schema::create('user_agreement_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('agreement_type', 16)->index();
            $table->string('agreement_version', 40)->index();
            $table->timestamp('accepted_at')->useCurrent()->index();
            $table->string('source', 32)->default('web')->index();
            $table->string('ip_address', 64)->nullable()->index();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['user_id', 'agreement_type', 'created_at'], 'idx_user_agreement_type_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_agreement_acceptances');
    }
};
