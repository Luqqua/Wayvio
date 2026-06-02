<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Forms\FormCatalog;
use App\Services\Forms\FormsAccess;
use App\Services\Forms\FormsBotProtectionVerifier;
use App\Services\Forms\FormsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FormsController extends Controller
{
    public function __construct(
        private readonly FormCatalog $catalog,
        private readonly FormsAccess $access,
        private readonly FormsClient $client,
        private readonly FormsBotProtectionVerifier $botProtectionVerifier,
    ) {
    }

    public function submit(Request $request, int $hub, string $formKey)
    {
        $formKey = $this->catalog->normalizeKey($formKey);
        $definition = $this->catalog->get($formKey);
        if (!$definition) {
            abort(404);
        }

        $hubUser = User::query()->findOrFail($hub);
        if (!$this->access->formsAllowedForHub($hubUser)) {
            abort(403);
        }

        $expectedContext = (string) ($definition['source_context'] ?? '');
        if ($expectedContext === '') {
            $this->logValidationFailure($request, $formKey, 'context_config_missing');
            throw ValidationException::withMessages(['forms' => __('The message could not be submitted.')]);
        }
        $submittedContext = trim((string) $request->input('source_context', ''));
        $normalizedContext = strtolower($submittedContext);
        $contextMissing = $submittedContext === '' || $normalizedContext === 'null' || $normalizedContext === 'undefined';
        if (!$contextMissing && $submittedContext !== $expectedContext) {
            $this->logValidationFailure($request, $formKey, 'context_mismatch', [
                'submitted_context' => $submittedContext,
                'expected_context' => $expectedContext,
            ]);
            throw ValidationException::withMessages(['forms' => __('The message could not be submitted.')]);
        }

        $this->validateHoneypot($request);
        $this->validateStartedAt($request);
        $this->validateBotProtection($request, $formKey);

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:254'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:4000'],
        ]);
        if ($validator->fails()) {
            $this->logValidationFailure($request, $formKey, 'payload_validation_failed', [
                'error_keys' => array_values(array_keys($validator->errors()->toArray())),
                'email_length' => strlen(trim((string) $request->input('email', ''))),
                'name_length' => strlen(trim((string) $request->input('name', ''))),
                'message_length' => strlen(trim((string) $request->input('message', ''))),
            ]);
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
        $validated = $validator->validated();

        $tenantOwnerUserId = $this->access->tenantOwnerUserIdForHub($hubUser);
        $actorUserId = $this->access->actorUserIdForPublicHub($hubUser);
        if (!$tenantOwnerUserId || !$actorUserId) {
            abort(403);
        }

        $response = $this->client->createSubmission([
            'actor_user_id' => $actorUserId,
            'tenant_owner_user_id' => $tenantOwnerUserId,
            'hub_user_id' => (int) $hubUser->id,
            'tier_level' => $this->access->tierLevelForHub($hubUser),
            'retention_days' => $this->access->retentionDaysForHub($hubUser),
            'submitter_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            'form_key' => $formKey,
            'source_context' => $expectedContext,
            'source_host' => $request->getHost(),
            'source_path' => '/' . ltrim($request->path(), '/'),
            'submitter_ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'turnstile_verified_at' => now()->toIso8601String(),
            'payload' => [
                'name' => trim((string) ($validated['name'] ?? '')),
                'email' => trim((string) $validated['email']),
                'subject' => trim((string) ($validated['subject'] ?? '')),
                'message' => trim((string) $validated['message']),
                'privacy_consent' => true,
            ],
        ]);

        if (empty($response['ok'])) {
            throw ValidationException::withMessages([
                'forms' => __('The message could not be submitted. Please try again later.'),
            ]);
        }

        return back()->with('forms_success_' . $formKey, __('Your message has been submitted.'));
    }

    private function validateHoneypot(Request $request): void
    {
        $field = (string) config('forms.honeypot_field', 'wayvio_company');
        if (trim((string) $request->input($field, '')) !== '') {
            $this->logValidationFailure($request, null, 'honeypot_filled', [
                'honeypot_field' => $field,
            ]);
            throw ValidationException::withMessages([
                'forms' => __('The message could not be submitted.'),
            ]);
        }
    }

    private function validateStartedAt(Request $request): void
    {
        if ($this->hasCaptchaToken($request)) {
            return;
        }

        $encrypted = (string) $request->input('_forms_started_at', '');
        if ($encrypted === '') {
            $this->logValidationFailure($request, null, 'started_at_missing');
            throw ValidationException::withMessages(['forms' => __('The message could not be submitted.')]);
        }

        try {
            $startedAt = (int) Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            $this->logValidationFailure($request, null, 'started_at_decrypt_failed');
            throw ValidationException::withMessages(['forms' => __('The message could not be submitted.')]);
        }

        $age = time() - $startedAt;
        $minAge = (int) config('forms.min_submit_seconds', 2);
        if ($age < $minAge || $age > 86400) {
            $this->logValidationFailure($request, null, 'started_at_age_out_of_range', [
                'age' => $age,
                'min_age' => $minAge,
            ]);
            throw ValidationException::withMessages(['forms' => __('The message could not be submitted.')]);
        }
    }

    private function hasCaptchaToken(Request $request): bool
    {
        foreach (['cap-token', 'cap_token', 'cf-turnstile-response', 'h-captcha-response', 'g-recaptcha-response'] as $field) {
            $value = $request->input($field);
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function validateBotProtection(Request $request, string $formKey): void
    {
        $this->botProtectionVerifier->validate($request, $formKey);
    }

    private function logValidationFailure(Request $request, ?string $formKey, string $reason, array $extra = []): void
    {
        Log::warning('Forms submit validation failed', array_merge([
            'reason' => $reason,
            'form_key' => $formKey,
            'host' => $request->getHost(),
            'path' => $request->path(),
            'has_source_context' => $request->has('source_context'),
            'source_context_raw' => $request->input('source_context'),
            'has_started_at' => $request->has('_forms_started_at'),
            'started_at_raw_length' => strlen((string) $request->input('_forms_started_at', '')),
        ], $extra));
    }
}
