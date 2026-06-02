<?php

namespace App\Services\Lifecycle;

use App\Models\User;
use App\Support\EmailLocaleResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LifecycleNotificationService
{
    /**
     * @param array<string,mixed> $payload
     */
    public function enqueue(
        int $userId,
        string $templateKey,
        array $payload = [],
        string $sourceEventType = 'billing.lifecycle',
        ?string $dedupeSeed = null
    ): bool {
        if ($userId <= 0 || trim($templateKey) === '' || !Schema::hasTable('billing_notification_outbox')) {
            return false;
        }

        $userSelect = ['id', 'email', 'name', 'locale'];
        if (Schema::hasColumn('users', 'last_login_locale')) {
            $userSelect[] = 'last_login_locale';
        }

        $user = User::query()->select($userSelect)->find($userId);
        if (!$user || !is_string($user->email) || trim($user->email) === '') {
            return false;
        }

        $emailLocale = EmailLocaleResolver::resolve(
            is_string($user->locale) ? $user->locale : null,
            is_string($user->last_login_locale ?? null) ? $user->last_login_locale : null
        );

        $normalizedPayload = array_merge([
            'user_name' => (string) ($user->name ?? ''),
            'locale' => $emailLocale,
        ], $payload);

        $dedupeInput = $dedupeSeed ?: implode('|', [
            $templateKey,
            $userId,
            $sourceEventType,
            (string) ($normalizedPayload['plan_slug'] ?? ''),
            (string) ($normalizedPayload['pending_plan_slug'] ?? ''),
            (string) ($normalizedPayload['effective_at'] ?? ''),
            (string) ($normalizedPayload['delete_at'] ?? ''),
            (string) ($normalizedPayload['hub_name'] ?? ''),
        ]);

        $dedupeKey = substr(hash('sha256', $dedupeInput), 0, 64);
        $jsonPayload = json_encode($normalizedPayload, JSON_UNESCAPED_SLASHES);
        if (!is_string($jsonPayload)) {
            $jsonPayload = null;
        }

        DB::table('billing_notification_outbox')->insertOrIgnore([
            'user_id' => $userId,
            'email' => strtolower(trim((string) $user->email)),
            'template_key' => substr(trim($templateKey), 0, 80),
            'dedupe_key' => $dedupeKey,
            'source_event_id' => null,
            'source_event_type' => substr(trim($sourceEventType), 0, 191),
            'payload' => $jsonPayload,
            'status' => 'pending',
            'attempt_count' => 0,
            'scheduled_for' => now(),
            'sent_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }
}
