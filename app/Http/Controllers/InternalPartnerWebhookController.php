<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Partners\Services\PartnerManager;

class InternalPartnerWebhookController extends Controller
{
    public function transferPaid(Request $request, PartnerManager $partnerManager): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['nullable', 'integer', 'min:1'],
            'batchId' => ['nullable', 'integer', 'min:1'],
            'stripe_reference' => ['nullable', 'string', 'max:191'],
            'stripeReference' => ['nullable', 'string', 'max:191'],
            'transfer_id' => ['nullable', 'string', 'max:191'],
            'transferId' => ['nullable', 'string', 'max:191'],
            'event_id' => ['nullable', 'string', 'max:191'],
            'transfer_group' => ['nullable', 'string', 'max:120'],
        ]);

        $batchId = (int) ($validated['batch_id'] ?? $validated['batchId'] ?? 0);
        $reference = (string) (
            $validated['stripe_reference']
            ?? $validated['stripeReference']
            ?? $validated['transfer_id']
            ?? $validated['transferId']
            ?? ''
        );
        $eventId = trim((string) ($validated['event_id'] ?? ''));
        $requestId = trim((string) ($request->headers->get('X-Request-Id') ?: $request->headers->get('X-Correlation-Id') ?: ''));
        $transferGroup = trim((string) ($validated['transfer_group'] ?? ''));

        if ($batchId < 1 || trim($reference) === '') {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_payload',
                'message' => 'batch_id and stripe_reference are required.',
            ], 422);
        }

        try {
            $batch = $partnerManager->settleManualPayoutBatch(
                $batchId,
                $reference,
                CarbonImmutable::now(),
                'internal_webhook',
            );

            $this->logSystemEvent('partner_payout_settled', 'info', [
                'batch_id' => (int) $batch->id,
                'partner_user_id' => (int) $batch->partner_user_id,
                'stripe_reference' => (string) $batch->stripe_transfer_id,
                'event_id' => $eventId !== '' ? $eventId : null,
                'transfer_group' => $transferGroup !== '' ? $transferGroup : null,
                'request_id' => $requestId !== '' ? $requestId : null,
            ]);

            return response()->json([
                'ok' => true,
                'batch_id' => (int) $batch->id,
                'partner_user_id' => (int) $batch->partner_user_id,
                'status' => (string) $batch->status,
                'stripe_reference' => (string) $batch->stripe_transfer_id,
            ]);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'Validation failed.';

            $this->logSystemEvent('partner_payout_settle_failed', 'warning', [
                'batch_id' => $batchId,
                'error' => $message,
                'event_id' => $eventId !== '' ? $eventId : null,
                'request_id' => $requestId !== '' ? $requestId : null,
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'validation_failed',
                'message' => $message,
            ], 422);
        } catch (\RuntimeException $exception) {
            $this->logSystemEvent('partner_payout_settle_failed', 'error', [
                'batch_id' => $batchId,
                'error' => $exception->getMessage(),
                'event_id' => $eventId !== '' ? $eventId : null,
                'request_id' => $requestId !== '' ? $requestId : null,
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'runtime_failed',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private function logSystemEvent(string $eventType, string $severity, array $context): void
    {
        if (!Schema::hasTable('system_events')) {
            return;
        }

        DB::table('system_events')->insert([
            'event_type' => $eventType,
            'severity' => $severity,
            'context' => json_encode($context, JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);
    }
}
