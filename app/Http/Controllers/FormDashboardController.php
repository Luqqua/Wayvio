<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use App\Services\Forms\FormCatalog;
use App\Services\Forms\FormsAccess;
use App\Services\Forms\FormsClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormDashboardController extends Controller
{
    public function __construct(
        private readonly AgencyHubContext $agencyContext,
        private readonly FormCatalog $catalog,
        private readonly FormsAccess $access,
        private readonly FormsClient $client,
    ) {
    }

    public function index(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $selectedHub = $this->activeHub($actor, $request);
        abort_unless($selectedHub instanceof User, 404);
        $this->denyTenantFormsContextMismatch($request, (int) $selectedHub->id);
        abort_unless($this->access->canActorAccessHub($actor, $selectedHub), 403);

        $formKey = $this->catalog->normalizeKey((string) $request->query('form_key', 'imprint_contact'));
        if (!$this->catalog->exists($formKey)) {
            $formKey = 'imprint_contact';
        }

        $formsAllowed = $this->access->formsAllowedForHub($selectedHub);
        $retentionDays = $this->access->retentionDaysForHub($selectedHub);
        $retentionMeta = $this->resolveRetentionMetaForHub($selectedHub, $formsAllowed, $retentionDays);

        $scope = $this->apiScope($actor, $selectedHub, $formKey);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) config('forms.dashboard_per_page', 25);
        $list = $this->client->listSubmissions(array_merge($scope, [
            'page' => $page,
            'per_page' => $perPage,
        ]));

        $selectedSubmission = null;
        $publicId = trim((string) $request->query('submission', ''));
        if ($publicId !== '') {
            $selectedSubmission = $this->client->getSubmission($publicId, $scope);
        }

        $newCountsByForm = [];
        foreach ($this->catalog->all() as $key => $definition) {
            $formScope = $this->apiScope($actor, $selectedHub, $key);
            $newResult = $this->client->listSubmissions(array_merge($formScope, [
                'status' => 'new',
                'per_page' => 1,
                'page' => 1,
            ]));
            $newCountsByForm[$key] = (int) ($newResult['total'] ?? 0);
        }

        return response()
            ->view('dashboard.forms.index', [
                'selectedHub' => $selectedHub,
                'forms' => $this->catalog->all(),
                'formKey' => $formKey,
                'list' => $list,
                'submissions' => (array) ($list['submissions'] ?? []),
                'selectedSubmission' => $selectedSubmission,
                'formsAllowed' => $formsAllowed,
                'retentionDays' => $retentionMeta['days'],
                'retentionMode' => $retentionMeta['mode'],
                'retentionGraceDays' => $retentionMeta['grace_days'],
                'retentionPendingDeletionAt' => $retentionMeta['pending_deletion_at'],
                'retentionDeleteAfterAt' => $retentionMeta['delete_after_at'],
                'retentionDaysUntilPendingDeletion' => $retentionMeta['days_until_pending_deletion'],
                'retentionDaysUntilDelete' => $retentionMeta['days_until_delete'],
                'retentionPendingDeletionEstimated' => $retentionMeta['pending_deletion_estimated'],
                'retentionDeleteAfterEstimated' => $retentionMeta['delete_after_estimated'],
                'newCountsByForm' => $newCountsByForm,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function delete(Request $request, string $publicId)
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $hub = $this->activeHub($actor, $request);
        abort_unless($hub instanceof User, 404);
        abort_unless($this->access->canActorAccessHub($actor, $hub), 403);

        $formKey = $this->catalog->normalizeKey((string) $request->input('form_key'));
        abort_unless($this->catalog->exists($formKey), 404);

        $result = $this->client->deleteSubmission($publicId, $this->apiScope($actor, $hub, $formKey));
        if (empty($result['ok'])) {
            return redirect()
                ->route('forms.dashboard', ['form_key' => $formKey])
                ->with('forms_dashboard_status', __('Submission could not be deleted.'));
        }

        return redirect()
            ->route('forms.dashboard', ['form_key' => $formKey])
            ->with('forms_dashboard_status', __('Submission deleted.'));
    }

    public function markRead(Request $request, string $publicId)
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $hub = $this->activeHub($actor, $request);
        abort_unless($hub instanceof User, 404);
        abort_unless($this->access->canActorAccessHub($actor, $hub), 403);

        $formKey = $this->catalog->normalizeKey((string) $request->input('form_key'));
        abort_unless($this->catalog->exists($formKey), 404);

        $result = $this->client->markRead($publicId, $this->apiScope($actor, $hub, $formKey));
        $message = empty($result['ok'])
            ? __('Submission could not be marked as read.')
            : __('Submission marked as read.');

        return redirect()
            ->route('forms.dashboard', [
                'form_key' => $formKey,
                'submission' => $publicId,
                'page' => max(1, (int) $request->input('page', 1)),
            ])
            ->with('forms_dashboard_status', $message);
    }

    public function markAllRead(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $hub = $this->activeHub($actor, $request);
        abort_unless($hub instanceof User, 404);
        abort_unless($this->access->canActorAccessHub($actor, $hub), 403);

        $formKey = $this->catalog->normalizeKey((string) $request->input('form_key'));
        abort_unless($this->catalog->exists($formKey), 404);

        $result = $this->client->markAllRead($this->apiScope($actor, $hub, $formKey));
        $updated = (int) ($result['updated'] ?? 0);
        $message = empty($result['ok'])
            ? __('Submissions could not be marked as read.')
            : __('Marked :count submissions as read.', ['count' => $updated]);

        return redirect()
            ->route('forms.dashboard', [
                'form_key' => $formKey,
                'page' => max(1, (int) $request->input('page', 1)),
            ])
            ->with('forms_dashboard_status', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $hub = $this->activeHub($actor, $request);
        abort_unless($hub instanceof User, 404);
        abort_unless($this->access->canActorAccessHub($actor, $hub), 403);

        $formKey = $this->catalog->normalizeKey((string) $request->query('form_key', ''));
        abort_unless($this->catalog->exists($formKey), 404);

        $result = $this->client->exportSubmissions($this->apiScope($actor, $hub, $formKey));
        $rows = (array) ($result['submissions'] ?? []);
        $filename = sprintf('wayvio-forms-%s-%s.csv', $formKey, now()->format('Ymd-His'));

        return response()->streamDownload(static function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['created_at', 'status', 'name', 'email', 'subject', 'message']);
            foreach ($rows as $row) {
                $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
                fputcsv($out, [
                    $row['created_at'] ?? '',
                    $row['status'] ?? '',
                    $payload['name'] ?? '',
                    $payload['email'] ?? '',
                    $payload['subject'] ?? '',
                    $payload['message'] ?? '',
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function activeHub(User $actor, Request $request): ?User
    {
        $activeHubId = (int) $this->agencyContext->editingUserId($actor, $request);
        if ($activeHubId <= 0) {
            return null;
        }

        return User::query()->find($activeHubId);
    }

    private function denyTenantFormsContextMismatch(Request $request, int $activeHubId): void
    {
        foreach (['hub_user_id', 'site_id', 'page_id', 'user_id'] as $queryKey) {
            if (!$request->query->has($queryKey)) {
                continue;
            }

            $rawValue = $request->query($queryKey);
            if (is_array($rawValue)) {
                $this->logAndAbortContextMismatch($request, $activeHubId, $queryKey, null);
            }

            $suppliedHubId = (int) $rawValue;
            if ($suppliedHubId <= 0 || $suppliedHubId !== $activeHubId) {
                $this->logAndAbortContextMismatch($request, $activeHubId, $queryKey, $suppliedHubId);
            }
        }
    }

    private function logAndAbortContextMismatch(
        Request $request,
        int $activeHubId,
        string $queryKey,
        ?int $suppliedHubId,
    ): void {
        Log::warning('Tenant forms context denied', [
            'reason_code' => 'tenant_page_context_mismatch_forms_dashboard',
            'actor_user_id' => (int) ($request->user()?->id ?? 0),
            'resource_user_id' => $suppliedHubId,
            'active_resource_user_id' => $activeHubId,
            'tenant_owner_user_id' => function_exists('currentTenantOwnerId') ? currentTenantOwnerId() : null,
            'query_key' => $queryKey,
            'route' => (string) ($request->route()?->getName() ?: $request->path()),
        ]);

        abort(403, 'Forbidden');
    }

    /**
     * @return array<string,mixed>
     */
    private function apiScope(User $actor, User $hub, string $formKey): array
    {
        $tenantOwnerUserId = $this->access->tenantOwnerUserIdForHub($hub);
        abort_unless($tenantOwnerUserId !== null, 403);

        return [
            'actor_user_id' => (int) $actor->id,
            'tenant_owner_user_id' => (int) $tenantOwnerUserId,
            'hub_user_id' => (int) $hub->id,
            'form_key' => $formKey,
            'source_context' => $this->catalog->expectedContext($formKey),
        ];
    }

    /**
     * @return array{
     *     days:int,
     *     mode:string,
     *     grace_days:int,
     *     pending_deletion_at:?string,
     *     delete_after_at:?string,
     *     days_until_pending_deletion:?int,
     *     days_until_delete:?int,
     *     pending_deletion_estimated:bool,
     *     delete_after_estimated:bool
     * }
     */
    private function resolveRetentionMetaForHub(User $hub, bool $formsAllowed, int $defaultRetentionDays): array
    {
        $graceDays = max(1, (int) config('billing.lifecycle.pending_deletion_grace_days', 1));
        $meta = [
            'days' => max(1, $defaultRetentionDays),
            'mode' => $formsAllowed ? 'plan' : 'locked',
            'grace_days' => $graceDays,
            'pending_deletion_at' => null,
            'delete_after_at' => null,
            'days_until_pending_deletion' => null,
            'days_until_delete' => null,
            'pending_deletion_estimated' => false,
            'delete_after_estimated' => false,
        ];

        if (!Schema::hasTable('forms_resource_states')) {
            return $meta;
        }

        $selectColumns = ['status', 'reason'];
        foreach (['suspended_at', 'pending_deletion_at', 'delete_after_at'] as $column) {
            if (Schema::hasColumn('forms_resource_states', $column)) {
                $selectColumns[] = $column;
            }
        }

        $state = DB::table('forms_resource_states')
            ->where('user_id', (int) $hub->id)
            ->select($selectColumns)
            ->first();

        if (!$state) {
            return $meta;
        }

        $status = strtolower(trim((string) ($state->status ?? '')));
        $reason = strtolower(trim((string) ($state->reason ?? '')));
        $isLifecycleReason = in_array($reason, ['downgrade', 'non_payment'], true);
        if (!$isLifecycleReason) {
            return $meta;
        }

        $now = now();
        $suspendedAt = $this->parseNullableCarbon($state->suspended_at ?? null);
        $pendingDeletionAt = $this->parseNullableCarbon($state->pending_deletion_at ?? null);
        $deleteAfterAt = $this->parseNullableCarbon($state->delete_after_at ?? null);

        if ($status === 'suspended') {
            $retentionDays = max(1, (int) config('billing.lifecycle.downgrade_retention_days', 30));
            $meta['days'] = $retentionDays;
            $meta['mode'] = 'lifecycle_suspended';

            if (!$pendingDeletionAt && $suspendedAt) {
                $pendingDeletionAt = $suspendedAt->copy()->addDays($retentionDays);
                $meta['pending_deletion_estimated'] = true;
            }

            if (!$deleteAfterAt && $pendingDeletionAt) {
                $deleteAfterAt = $pendingDeletionAt->copy()->addDays($graceDays);
                $meta['delete_after_estimated'] = true;
            }

            $meta['pending_deletion_at'] = $pendingDeletionAt?->toIso8601String();
            $meta['delete_after_at'] = $deleteAfterAt?->toIso8601String();
            $meta['days_until_pending_deletion'] = $pendingDeletionAt
                ? $this->remainingDaysUntil($pendingDeletionAt, $now)
                : null;
            $meta['days_until_delete'] = $deleteAfterAt
                ? $this->remainingDaysUntil($deleteAfterAt, $now)
                : null;
            return $meta;
        }

        if ($status === 'pending_deletion') {
            $meta['days'] = $graceDays;
            $meta['mode'] = 'lifecycle_pending_deletion';

            if (!$pendingDeletionAt && $deleteAfterAt) {
                $pendingDeletionAt = $deleteAfterAt->copy()->subDays($graceDays);
                $meta['pending_deletion_estimated'] = true;
            }
            if (!$deleteAfterAt && $pendingDeletionAt) {
                $deleteAfterAt = $pendingDeletionAt->copy()->addDays($graceDays);
                $meta['delete_after_estimated'] = true;
            }

            $meta['pending_deletion_at'] = $pendingDeletionAt?->toIso8601String();
            $meta['delete_after_at'] = $deleteAfterAt?->toIso8601String();
            $meta['days_until_pending_deletion'] = $pendingDeletionAt
                ? $this->remainingDaysUntil($pendingDeletionAt, $now)
                : null;
            $meta['days_until_delete'] = $deleteAfterAt
                ? $this->remainingDaysUntil($deleteAfterAt, $now)
                : null;
        }

        return $meta;
    }

    private function parseNullableCarbon(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function remainingDaysUntil(Carbon $targetAt, Carbon $now): int
    {
        $seconds = $targetAt->getTimestamp() - $now->getTimestamp();
        if ($seconds <= 0) {
            return 0;
        }

        return (int) ceil($seconds / 86400);
    }
}
