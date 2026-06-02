<?php

namespace App\Http\Controllers;

use App\Models\AgencyHub;
use App\Services\Lifecycle\AccountLifecycleService;
use App\Services\Agency\AgencyHubContext;
use App\Services\Agency\AgencyHubQuotaManager;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\Hubs\HubPublicationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Tiers\Models\UserSubscription;

class AgencyHubController extends Controller
{
    public function __construct(
        private readonly AgencyHubContext $agencyContext,
        private readonly AgencyHubQuotaManager $quotaManager,
        private readonly ComplianceAuditService $complianceAudit,
        private readonly AccountLifecycleService $lifecycleService,
        private readonly HubPublicationService $hubPublicationService,
    )
    {
    }

    public function index(Request $request): View
    {
        $agencyUser = $request->user();
        if (!$this->agencyContext->isAgencyAccount($agencyUser)) {
            abort(403, 'Managed hubs are only available on the Agency plan.');
        }

        $sidebar = $this->agencyContext->sidebarData($agencyUser, $request);
        $managedHubs = AgencyHub::query()
            ->with('managedUser')
            ->where('agency_user_id', (int) $agencyUser->id)
            ->orderBy('created_at')
            ->get();

        $subscription = UserSubscription::query()
            ->where('user_id', (int) $agencyUser->id)
            ->first();
        $graceEndsAt = $subscription?->agency_grace_period_ends_at
            ? Carbon::parse($subscription->agency_grace_period_ends_at)
            : null;
        $graceDaysLeft = $graceEndsAt ? max(0, now()->diffInDays($graceEndsAt, false)) : null;
        $graceOverQuota = (int) ($subscription?->agency_over_quota_count ?? 0);
        $reactivationSlotsAvailable = max(0, (int) ($sidebar['slots']['available'] ?? 0));

        return view('agency.hubs', [
            'agencySidebar' => $sidebar,
            'managedHubs' => $managedHubs,
            'reactivationSlotsAvailable' => $reactivationSlotsAvailable,
            'graceBanner' => [
                'active' => $graceEndsAt !== null && now()->lt($graceEndsAt),
                'over_quota' => $graceOverQuota,
                'days_left' => $graceDaysLeft,
                'ends_at' => $graceEndsAt?->toIso8601String(),
            ],
        ]);
    }

    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'managed_user_id' => ['required', 'integer', 'min:1'],
        ]);

        $switched = $this->agencyContext->switchHub(
            $request->user(),
            (int) $validated['managed_user_id'],
            $request
        );

        if (!$switched) {
            return back()->withErrors([
                'hub' => 'The selected hub is not available for your agency account.',
            ]);
        }

        return redirect('/studio/links')->with('success', 'Hub context updated.');
    }

    public function store(Request $request): RedirectResponse
    {
        $agencyUser = $request->user();

        if (!$this->agencyContext->isAgencyAccount($agencyUser)) {
            abort(403, 'Managed hubs are only available on the Agency plan.');
        }

        $locale = strtolower((string) ($agencyUser->locale ?? app()->getLocale() ?? 'en'));
        $isGerman = str_starts_with($locale, 'de');

        $normalizedSlug = Str::lower(trim((string) $request->input('littlelink_name', '')));
        $request->merge(['littlelink_name' => $normalizedSlug]);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:30'],
            'littlelink_name' => [
                'required',
                'string',
                'min:2',
                'max:25',
                'regex:/^[a-z0-9._-]+$/',
                Rule::notIn(reservedSlugs()),
                'isunique:users,id,0',
            ],
            'littlelink_description' => ['nullable', 'string', 'max:255'],
        ], [
            'littlelink_name.regex' => $isGerman
                ? 'Der Hub-Slug darf nur Kleinbuchstaben, Zahlen, Punkte, Bindestriche und Unterstriche enthalten (keine Leerzeichen).'
                : 'The hub slug may only contain lowercase letters, numbers, dots, dashes, and underscores (no spaces).',
            'littlelink_name.isunique' => $isGerman
                ? 'Dieser Hub-Slug ist bereits vergeben. Bitte wähle einen anderen.'
                : 'This hub slug is already taken. Please choose a different one.',
            'littlelink_name.not_in' => $isGerman
                ? 'Dieser Hub-Slug ist reserviert. Bitte wähle einen anderen.'
                : 'This hub slug is reserved. Please choose a different one.',
        ], [
            'littlelink_name' => $isGerman ? 'Hub-Slug' : 'hub slug',
        ]);

        $hub = $this->agencyContext->createHub($agencyUser, [
            'display_name' => trim(strip_tags((string) $validated['display_name'])),
            'littlelink_name' => trim((string) $validated['littlelink_name']),
            'littlelink_description' => trim(strip_tags((string) ($validated['littlelink_description'] ?? ''))),
        ], $request);

        $this->complianceAudit->record(
            'hub_created',
            request: $request,
            userId: (int) $agencyUser->id,
            actorUserId: (int) $agencyUser->id,
            source: 'agency.hub',
            metadata: [
                'managed_user_id' => (int) ($hub->managed_user_id ?? 0),
                'managed_slug' => $hub->managedUser?->littlelink_name,
                'display_name' => (string) ($hub->display_name ?? ''),
            ]
        );

        return redirect()->route('agency.hubs.index')->with('success', 'Managed hub created.');
    }

    public function prune(Request $request): RedirectResponse
    {
        $agencyUser = $request->user();
        if (!$this->agencyContext->isAgencyAccount($agencyUser)) {
            abort(403, 'Managed hubs are only available on the Agency plan.');
        }

        $validated = $request->validate([
            'managed_user_ids' => ['nullable', 'array'],
            'managed_user_ids.*' => ['integer', 'min:1'],
            'strategy' => ['nullable', Rule::in(AgencyHubQuotaManager::STRATEGIES)],
        ]);

        $targetSlots = (int) ($this->agencyContext->slotSummary($agencyUser)['total'] ?? 0);
        $preferredDelete = collect($validated['managed_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $result = $this->quotaManager->enforceManagedHubLimit(
            $agencyUser,
            $targetSlots,
            $preferredDelete,
            (string) ($validated['strategy'] ?? AgencyHubQuotaManager::STRATEGY_LEAST_LINKS),
            false
        );

        $suspended = (int) ($result['removed'] ?? 0);
        if ($suspended <= 0) {
            return back()->with('success', 'No hubs deactivated. You are already within quota.');
        }

        $labels = collect((array) ($result['removed_hubs'] ?? []))
            ->map(function (array $hub): string {
                $label = trim((string) ($hub['display_name'] ?? ''));
                $slug = trim((string) ($hub['slug'] ?? ''));
                if ($label !== '' && $slug !== '') {
                    return "{$label} ({$slug})";
                }
                if ($slug !== '') {
                    return $slug;
                }
                if ($label !== '') {
                    return $label;
                }
                return '#'.(int) ($hub['managed_user_id'] ?? 0);
            })
            ->filter()
            ->implode(', ');

        $this->complianceAudit->record(
            'hub_suspended_by_user',
            request: $request,
            userId: (int) $agencyUser->id,
            actorUserId: (int) $agencyUser->id,
            source: 'agency.hub',
            metadata: [
                'mode' => 'prune',
                'suspended_count' => $suspended,
                'suspended_hubs' => array_values((array) ($result['removed_hubs'] ?? [])),
            ]
        );

        return back()->with('success', 'Deactivated '.$suspended.' hub(s): '.$labels);
    }

    public function destroy(Request $request, int $managedUserId): RedirectResponse
    {
        $agencyUser = $request->user();
        if (!$this->agencyContext->isAgencyAccount($agencyUser)) {
            abort(403, 'Managed hubs are only available on the Agency plan.');
        }

        if ($managedUserId <= 0 || $managedUserId === (int) $agencyUser->id) {
            return back()->withErrors([
                'hub' => 'Invalid managed hub selection.',
            ]);
        }

        $hub = AgencyHub::query()
            ->where('agency_user_id', $agencyUser->id)
            ->where('managed_user_id', $managedUserId)
            ->first();
        if (!$hub) {
            return back()->withErrors([
                'hub' => 'The selected hub is not available for your agency account.',
            ]);
        }

        $status = (string) ($hub->status ?? 'active');
        if ($status === 'active') {
            $suspended = $this->lifecycleService->suspendHubByUser($agencyUser, $managedUserId);
            if (!$suspended) {
                return back()->withErrors([
                    'hub' => 'Hub could not be deactivated.',
                ]);
            }

            $this->complianceAudit->record(
                'hub_suspended_by_user',
                request: $request,
                userId: (int) $agencyUser->id,
                actorUserId: (int) $agencyUser->id,
                source: 'agency.hub',
                metadata: [
                    'mode' => 'single',
                    'managed_user_id' => $managedUserId,
                ]
            );

            return back()->with('success', 'Managed hub deactivated.');
        }

        if (!in_array($status, ['suspended', 'pending_deletion'], true)) {
            return back()->withErrors([
                'hub' => 'This hub cannot be deleted in its current state.',
            ]);
        }

        $deleted = $this->lifecycleService->permanentlyDeleteHubByUser($agencyUser, $managedUserId);
        if (!$deleted) {
            return back()->withErrors([
                'hub' => 'Hub could not be deleted. Please try again.',
            ]);
        }

        $this->complianceAudit->record(
            'hub_deleted_by_user',
            request: $request,
            userId: (int) $agencyUser->id,
            actorUserId: (int) $agencyUser->id,
            source: 'agency.hub',
            metadata: [
                'managed_user_id' => $managedUserId,
            ]
        );

        return back()->with('success', 'Managed hub permanently deleted.');
    }

    public function setPublication(Request $request, int $managedUserId): RedirectResponse
    {
        $agencyUser = $request->user();
        if (!$this->agencyContext->isAgencyAccount($agencyUser)) {
            abort(403, 'Managed hubs are only available on the Agency plan.');
        }

        $locale = strtolower((string) ($agencyUser->locale ?? app()->getLocale() ?? 'en'));
        $isGerman = str_starts_with($locale, 'de');

        $validated = $request->validate([
            'publish' => ['required', 'boolean'],
        ]);

        $hub = AgencyHub::query()
            ->with('managedUser')
            ->where('agency_user_id', (int) $agencyUser->id)
            ->where('managed_user_id', $managedUserId)
            ->first();

        if (!$hub || !$hub->managedUser) {
            return back()->withErrors([
                'hub' => $isGerman
                    ? 'Der ausgewählte Hub ist für deinen Agency-Account nicht verfügbar.'
                    : 'The selected hub is not available for your agency account.',
            ]);
        }

        if ((string) ($hub->status ?? 'active') !== 'active') {
            return back()->withErrors([
                'hub' => __('messages.hub.publish.suspended'),
            ]);
        }

        if (!$this->hubPublicationService->publicationColumnsReady()) {
            return back()->withErrors([
                'hub' => __('messages.hub.publish.error_unavailable'),
            ]);
        }

        if (!$this->hubPublicationService->canManagePublication($agencyUser, $hub->managedUser)) {
            abort(403);
        }

        $publish = (bool) $validated['publish'];
        $this->hubPublicationService->setPublicationState(
            $agencyUser,
            $hub->managedUser,
            $publish,
            $request,
            'agency.hub.publication'
        );

        $message = $publish
            ? __('messages.hub.publish.success_published')
            : __('messages.hub.publish.success_unpublished');

        return back()->with('success', $message);
    }

    public function reactivate(Request $request, int $managedUserId): RedirectResponse
    {
        $agencyUser = $request->user();
        if (!$this->agencyContext->isAgencyAccount($agencyUser)) {
            abort(403, 'Managed hubs are only available on the Agency plan.');
        }

        if ($managedUserId <= 0 || $managedUserId === (int) $agencyUser->id) {
            return back()->withErrors([
                'hub' => 'Invalid managed hub selection.',
            ]);
        }

        $availableSlots = max(0, (int) ($this->agencyContext->slotSummary($agencyUser)['available'] ?? 0));
        if ($availableSlots <= 0) {
            return back()->withErrors([
                'hub' => 'Hub could not be reactivated. Ensure a free hub slot is available.',
            ]);
        }

        $reactivated = $this->lifecycleService->reactivateHubByUser($agencyUser, $managedUserId, false);
        if (!$reactivated) {
            return back()->withErrors([
                'hub' => 'Hub could not be reactivated. The selected hub may no longer be suspended.',
            ]);
        }

        $this->complianceAudit->record(
            'hub_reactivated_by_user',
            request: $request,
            userId: (int) $agencyUser->id,
            actorUserId: (int) $agencyUser->id,
            source: 'agency.hub',
            metadata: [
                'managed_user_id' => $managedUserId,
            ]
        );

        return back()->with('success', 'Managed hub reactivated.');
    }
}
