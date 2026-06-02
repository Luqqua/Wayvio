<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->longText('data')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // One-time backfill from legacy storage in users.image when value is JSON object.
        DB::table('users')
            ->select('id', 'image')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                $now = now();
                $rows = [];

                foreach ($users as $user) {
                    if (!is_string($user->image)) {
                        continue;
                    }

                    $raw = trim($user->image);
                    if ($raw === '') {
                        continue;
                    }

                    $decoded = json_decode($raw, true);
                    if (!is_array($decoded) || array_values($decoded) === $decoded || $decoded === []) {
                        continue;
                    }

                    $rows[] = [
                        'user_id' => (int) $user->id,
                        'data' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('user_settings')->upsert(
                        $rows,
                        ['user_id'],
                        ['data', 'updated_at']
                    );
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
