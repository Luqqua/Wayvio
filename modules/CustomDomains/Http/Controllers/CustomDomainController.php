<?php

namespace Modules\CustomDomains\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AgencyHub;
use App\Models\UserData;
use App\Services\Agency\AgencyHubContext;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\Domains\DomainsClient;
use App\Services\Uploads\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\CustomDomains\Models\UserCustomDomain;
use Modules\Tiers\Services\SubscriptionManager;

class CustomDomainController extends Controller
{
    public function __construct(
        private SubscriptionManager $subscriptionManager,
        private DomainsClient $domainsClient,
        private AgencyHubContext $agencyContext,
        private ComplianceAuditService $complianceAudit,
    ) {
    }

    protected function guardTier($user): void
    {
        if (!$this->subscriptionManager->featureEnabled($user, 'domains.custom_domain')) {
            abort(403, 'Custom domains are not enabled for your tier');
        }
    }

    public function index(Request $request)
    {
        $this->guardTier($request->user());
        $scope = $request->query('scope');
        $requestedPageId = $request->filled('page_id') ? (int) $request->query('page_id') : null;

        if (!$this->domainsClient->enabled()) {
            return $this->domainsUnavailable('domains_api_disabled');
        }

        $domains = $this->domainsClient->listByUser($request->user()->id, $request->user()->id);
        if ($forwarded = $this->forwardDomainsClientError($domains)) {
            return $forwarded;
        }

        if (is_array($domains)) {
            $ownedDomains = $this->filterDomainsOwnedByUser((int) $request->user()->id, $domains);

            return response()
                ->json($this->sanitizeDomainsForFrontend($this->filterDomainsForScope($request, $ownedDomains, $scope, $requestedPageId)))
                ->header('Cache-Control', 'private, no-store, max-age=0, must-revalidate')
                ->header('Pragma', 'no-cache');
        }

        return $this->domainsUnavailable();
    }

    public function store(Request $request)
    {
        $this->guardTier($request->user());
        $isAgencyAccount = $this->agencyContext->isAgencyAccount($request->user());
        $whiteLabelEnabled = $this->subscriptionManager->featureEnabled($request->user(), 'agency.white_label');

        $request->validate([
            'domain' => [
                'required',
                'string',
                'max:253',
                function (string $attribute, mixed $value, $fail): void {
                    if (!is_string($value)) {
                        $fail('Invalid domain.');
                        return;
                    }

                    $candidate = strtolower(trim($value));
                    if ($candidate === '' || preg_match('/\s/', $candidate)) {
                        $fail('Invalid domain.');
                        return;
                    }
                    if (str_contains($candidate, '://') || str_contains($candidate, '/') || str_contains($candidate, ':') || str_contains($candidate, '*')) {
                        $fail('Invalid domain.');
                        return;
                    }

                    $normalized = trim($candidate, ". \t\n\r\0\x0B");
                    if (!str_contains($normalized, '.')) {
                        $fail('Domain must include a TLD.');
                        return;
                    }
                    if (!filter_var($normalized, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                        $fail('Invalid domain.');
                    }
                },
            ],
            'scope' => [
                'nullable',
                Rule::in(['agency', 'hub']),
            ],
            'page_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $domainInput = strtolower(trim($request->input('domain')));
        $scope = $request->input('scope', 'hub');
        $requestedPageId = $request->filled('page_id') ? (int) $request->input('page_id') : null;

        if (
            Schema::hasTable('user_custom_domains')
            && UserCustomDomain::query()->whereRaw('LOWER(domain) = ?', [$domainInput])->exists()
        ) {
            return response()->json([
                'error' => 'domain_exists',
                'message' => 'Domain already exists',
            ], 422);
        }

        if ($scope === 'agency' && !$whiteLabelEnabled) {
            abort(403, 'Agency-level domains are only available on the Agency plan.');
        }

        $targetPageId = $this->resolveScopedPageId($request, $scope, $requestedPageId);
        if ($targetPageId !== null) {
            $this->authorizeResourceAccess($request, $targetPageId);
        } else {
            $this->authorizeResourceAccess($request, (int) $request->user()->id);
        }

        if ($this->scopeAlreadyHasDomain((int) $request->user()->id, $targetPageId)) {
            return response()->json([
                'error' => 'domain_scope_limit_reached',
                'message' => 'Only one custom domain is allowed here. Remove the existing domain before adding another one.',
            ], 422);
        }

        if ($scope !== 'agency' && $isAgencyAccount) {
            if ($targetPageId === (int) $request->user()->id) {
                return response()->json([
                    'error' => 'Hub domains are only available for managed hubs. Use agency domain for global routing.',
                ], 422);
            }
        }

        if (!$this->domainsClient->enabled()) {
            return $this->domainsUnavailable('domains_api_disabled');
        }

        $result = $this->domainsClient->createDomain($request->user()->id, $domainInput, $targetPageId, $request->user()->id);
        if ($forwarded = $this->forwardDomainsClientError($result)) {
            return $forwarded;
        }

        if (is_array($result) && isset($result['domain'])) {
            $this->complianceAudit->record(
                'domain_set',
                request: $request,
                userId: (int) $request->user()->id,
                actorUserId: (int) $request->user()->id,
                source: 'domains',
                metadata: [
                    'domain_id' => (int) ($result['id'] ?? 0),
                    'domain' => (string) ($result['domain'] ?? $domainInput),
                    'scope' => $scope,
                    'page_id' => $targetPageId,
                    'status' => (string) ($result['status'] ?? ''),
                ]
            );

            return response()->json($this->sanitizeDomainForFrontend($result));
        }

        return $this->domainsUnavailable();
    }

    public function verify(Request $request, UserCustomDomain $domain)
    {
        $this->authorizeOwner($request, $domain);

        if (strtolower((string) $domain->status) === 'verified') {
            return response()->json($this->sanitizeDomainForFrontend(($domain->fresh() ?? $domain)->toArray()));
        }

        if (!$this->domainsClient->enabled()) {
            return $this->domainsUnavailable('domains_api_disabled');
        }

        $result = $this->domainsClient->triggerVerify($request->user()->id, $domain->id, $request->user()->id);
        if ($forwarded = $this->forwardDomainsClientError($result)) {
            return $forwarded;
        }

        if (is_array($result)) {
            $fresh = UserCustomDomain::find($domain->id);

            $this->complianceAudit->record(
                'domain_verified',
                request: $request,
                userId: (int) $request->user()->id,
                actorUserId: (int) $request->user()->id,
                source: 'domains',
                metadata: [
                    'domain_id' => (int) $domain->id,
                    'domain' => (string) ($domain->domain ?? ''),
                    'status' => (string) ($fresh->status ?? $domain->status ?? ''),
                ]
            );

            return response()->json($fresh ?? $domain);
        }

        return $this->domainsUnavailable();
    }

    public function destroy(Request $request, UserCustomDomain $domain)
    {
        $this->authorizeOwner($request, $domain);

        if (!$this->domainsClient->enabled()) {
            return $this->domainsUnavailable('domains_api_disabled');
        }

        $result = $this->domainsClient->deleteDomain($request->user()->id, $domain->id, $request->user()->id);
        if ($forwarded = $this->forwardDomainsClientError($result)) {
            return $forwarded;
        }

        if (is_array($result)) {
            $this->complianceAudit->record(
                'domain_removed',
                request: $request,
                userId: (int) $request->user()->id,
                actorUserId: (int) $request->user()->id,
                source: 'domains',
                metadata: [
                    'domain_id' => (int) $domain->id,
                    'domain' => (string) ($domain->domain ?? ''),
                    'status' => 'deleted',
                ]
            );

            return response()->json(['status' => 'deleted']);
        }

        return $this->domainsUnavailable();
    }

    protected function authorizeOwner(Request $request, UserCustomDomain $domain): void
    {
        Gate::forUser($request->user())->authorize('update', $domain);
        $this->guardTier($request->user());
    }

    protected function domainsUnavailable(string $error = 'domains_api_unavailable')
    {
        return response()->json(['error' => $error], 503);
    }

    protected function forwardDomainsClientError(?array $response): ?JsonResponse
    {
        if (!$this->domainsClient->isErrorResponse($response)) {
            return null;
        }

        $status = $this->domainsClient->errorStatus($response) ?? 422;
        $payload = $this->domainsClient->errorPayload($response);
        $safePayload = $this->sanitizeDomainsClientErrorPayload($payload, $status);

        Log::warning('Custom domains upstream error sanitized for frontend', [
            'status' => $status,
            'safe_error' => $safePayload['error'] ?? null,
            'safe_message' => $safePayload['message'] ?? null,
            'upstream_error_code' => $this->extractForwardedErrorCode($payload),
            'upstream_message' => $this->extractForwardedErrorMessage($payload),
        ]);

        return response()->json(
            $safePayload,
            $status
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{error:string,message:string}
     */
    protected function sanitizeDomainsClientErrorPayload(array $payload, int $status): array
    {
        if ($status === 429) {
            return [
                'error' => 'domains_rate_limited',
                'message' => 'Too many domain requests. Please wait a moment and try again.',
            ];
        }

        if ($status >= 500) {
            return [
                'error' => 'domains_api_unavailable',
                'message' => 'Custom domain service is temporarily unavailable. Please try again shortly.',
            ];
        }

        if ($status === 403) {
            return [
                'error' => 'domains_request_forbidden',
                'message' => 'This domain action is currently not allowed.',
            ];
        }

        if ($status === 404) {
            return [
                'error' => 'domain_not_found',
                'message' => 'The selected domain could not be found.',
            ];
        }

        return [
            'error' => 'domains_request_rejected',
            'message' => 'The domain request could not be completed. Please check your input and try again.',
        ];
    }

    protected function extractForwardedErrorMessage(array $payload): ?string
    {
        $directMessage = $payload['message'] ?? null;
        if (is_string($directMessage) && trim($directMessage) !== '') {
            return trim($directMessage);
        }

        $error = $payload['error'] ?? null;
        if (is_string($error) && trim($error) !== '') {
            return trim($error);
        }

        if (is_array($error)) {
            $nestedMessage = $error['message'] ?? null;
            if (is_string($nestedMessage) && trim($nestedMessage) !== '') {
                return trim($nestedMessage);
            }

            $nestedCode = $error['code'] ?? null;
            if (is_string($nestedCode) && trim($nestedCode) !== '') {
                return trim($nestedCode);
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    protected function extractForwardedErrorCode(array $payload): ?string
    {
        $error = $payload['error'] ?? null;
        if (is_string($error) && trim($error) !== '') {
            return trim($error);
        }

        if (is_array($error)) {
            $nestedCode = $error['code'] ?? null;
            if (is_string($nestedCode) && trim($nestedCode) !== '') {
                return trim($nestedCode);
            }
        }

        return null;
    }

    public function saveAgencyBranding(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->guardTier($user);
        $mediaStorage = app(MediaStorageService::class);
        $logoLimit = $this->imageUploadLimit('agency_logo', 3072, 2500, 2500);

        if (!$this->subscriptionManager->featureEnabled($user, 'agency.white_label')) {
            abort(403, 'Agency branding is only available on the Agency plan.');
        }

        $request->merge([
            'branding_link' => $this->normalizeOptionalBrandingLink($request->input('branding_link')),
            'remove_branding_asset' => $request->boolean('remove_branding_asset') ? '1' : '0',
        ]);

        $request->validate([
            'branding_asset' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:'.$logoLimit['max_kb'],
                'dimensions:max_width='.$logoLimit['max_width'].',max_height='.$logoLimit['max_height'],
            ],
            'branding_link' => ['nullable', 'url', 'max:255'],
            'remove_branding_asset' => ['nullable', 'boolean'],
        ], [
            'branding_asset.image' => __('messages.The selected file must be an image'),
            'branding_asset.mimes' => __('messages.The image must be') . ' JPEG, JPG, PNG, webP.',
            'branding_asset.max' => __('messages.upload.validation.max', [
                'label' => __('messages.Agency logo'),
                'size' => $logoLimit['max_mb'],
            ]),
            'branding_asset.dimensions' => __('messages.upload.validation.dimensions', [
                'label' => __('messages.Agency logo'),
                'width' => $logoLimit['max_width'],
                'height' => $logoLimit['max_height'],
            ]),
        ]);

        try {
            $userId = (int) $user->id;
            $currentAssetPath = UserData::getData($userId, 'agency_branding_asset');
            $removeBrandingAsset = $request->boolean('remove_branding_asset');
            $logoUploaded = false;
            $logoRemoved = false;

            if ($removeBrandingAsset) {
                $this->deleteAgencyBrandingAsset($currentAssetPath);
                UserData::removeData($userId, 'agency_branding_asset');
                $currentAssetPath = null;
                $logoRemoved = true;
            }

            $brandingAsset = $request->file('branding_asset');
            if (!$removeBrandingAsset && $brandingAsset) {
                $storedBrandingPath = $mediaStorage->storeUploadedFile($userId, $brandingAsset, 'agency-branding');
                UserData::saveData($userId, 'agency_branding_asset', $storedBrandingPath);
                if (is_string($currentAssetPath) && trim($currentAssetPath) !== '' && $currentAssetPath !== $storedBrandingPath) {
                    $this->deleteAgencyBrandingAsset($currentAssetPath);
                }
                $logoUploaded = true;
            }

            $brandingLink = trim((string) $request->input('branding_link', ''));
            if ($brandingLink === '') {
                UserData::removeData($userId, 'agency_branding_link');
            } else {
                UserData::saveData($userId, 'agency_branding_link', $brandingLink);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Agency branding save failed', [
                'user_id' => (int) ($user->id ?? 0),
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('domains.page')
                ->withInput()
                ->withErrors(['branding' => 'Agency branding could not be saved. Please try again.']);
        }

        if ($logoRemoved) {
            $successMessage = 'Logo removed.';
        } elseif ($logoUploaded) {
            $successMessage = 'Agency logo uploaded successfully.';
        } else {
            $successMessage = 'Branding settings saved.';
        }

        return redirect()->route('domains.page')->with('success', $successMessage);
    }

    protected function normalizeOptionalBrandingLink(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '' || strtolower($normalized) === 'null') {
            return null;
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $domains
     * @return array<int,array<string,mixed>>
     */
    protected function filterDomainsForScope(Request $request, array $domains, ?string $scope, ?int $requestedPageId): array
    {
        if ($scope === 'agency') {
            return array_values(array_filter($domains, static function (array $domain): bool {
                return !array_key_exists('page_id', $domain) || $domain['page_id'] === null;
            }));
        }

        if ($scope === 'hub' || $requestedPageId !== null) {
            $targetPageId = $this->resolveScopedPageId($request, 'hub', $requestedPageId);

            return array_values(array_filter($domains, static function (array $domain) use ($targetPageId): bool {
                return (int) ($domain['page_id'] ?? 0) === $targetPageId;
            }));
        }

        return array_values($domains);
    }

    protected function scopeAlreadyHasDomain(int $userId, ?int $pageId): bool
    {
        if (!Schema::hasTable('user_custom_domains')) {
            return false;
        }

        $query = UserCustomDomain::query()->where('user_id', $userId);

        if (Schema::hasColumn('user_custom_domains', 'lifecycle_status')) {
            $query->where(function ($query): void {
                $query
                    ->whereNull('lifecycle_status')
                    ->orWhere('lifecycle_status', '!=', 'deleted');
            });
        }

        if ($pageId === null) {
            $query->whereNull('page_id');
        } else {
            $query->where('page_id', $pageId);
        }

        return $query->exists();
    }

    /**
     * @param array<int,array<string,mixed>> $domains
     * @return array<int,array<string,mixed>>
     */
    protected function filterDomainsOwnedByUser(int $userId, array $domains): array
    {
        $owned = array_values(array_filter($domains, static function (array $domain) use ($userId): bool {
            if (!array_key_exists('user_id', $domain)) {
                return false;
            }

            return (int) ($domain['user_id'] ?? 0) === $userId;
        }));

        $dropped = count($domains) - count($owned);
        if ($dropped > 0) {
            Log::warning('Filtered foreign domains from internal domains response', [
                'user_id' => $userId,
                'total_domains' => count($domains),
                'dropped_domains' => $dropped,
            ]);
        }

        return $owned;
    }

    /**
     * @param array<int,array<string,mixed>> $domains
     * @return array<int,array<string,mixed>>
     */
    protected function sanitizeDomainsForFrontend(array $domains): array
    {
        return array_values(array_map(
            fn (array $domain): array => $this->sanitizeDomainForFrontend($domain),
            $domains
        ));
    }

    /**
     * @param array<string,mixed> $domain
     * @return array<string,mixed>
     */
    protected function sanitizeDomainForFrontend(array $domain): array
    {
        unset($domain['cloudflare_verification_errors']);

        return $domain;
    }

    protected function resolveScopedPageId(Request $request, string $scope, ?int $requestedPageId): ?int
    {
        $user = $request->user();
        $isAgencyAccount = $this->agencyContext->isAgencyAccount($user);
        $whiteLabelEnabled = $this->subscriptionManager->featureEnabled($user, 'agency.white_label');

        if ($scope === 'agency') {
            if (!$whiteLabelEnabled) {
                abort(403, 'Agency-level domains are only available on the Agency plan.');
            }

            return null;
        }

        if (!$isAgencyAccount) {
            if ($requestedPageId !== null && $requestedPageId !== (int) $user->id) {
                abort(422, 'Invalid page scope for this account.');
            }

            $this->authorizeResourceAccess($request, (int) $user->id);
            return (int) $user->id;
        }

        $targetPageId = $requestedPageId ?? $this->agencyContext->editingUserId($user, $request);
        if ($targetPageId === (int) $user->id) {
            $this->authorizeResourceAccess($request, (int) $user->id);
            return (int) $user->id;
        }

        $isOwnedManagedHub = AgencyHub::query()
            ->where('agency_user_id', $user->id)
            ->where('managed_user_id', $targetPageId)
            ->where('status', 'active')
            ->exists();

        if (!$isOwnedManagedHub) {
            abort(422, 'Invalid hub selection.');
        }

        $this->authorizeResourceAccess($request, $targetPageId);
        return $targetPageId;
    }

    protected function authorizeResourceAccess(Request $request, int $resourceUserId): void
    {
        Gate::forUser($request->user())->authorize('tenant.access-resource', $resourceUserId);
    }

    protected function deleteAgencyBrandingAsset(mixed $path): void
    {
        if (!is_string($path) || trim($path) === '') {
            return;
        }

        app(MediaStorageService::class)->delete($path);
    }

    /**
     * @return array{max_kb:int,max_width:int,max_height:int,max_mb:int}
     */
    protected function imageUploadLimit(string $limitKey, int $defaultKb, int $defaultWidth, int $defaultHeight): array
    {
        $limitConfig = config('media.upload_limits.' . $limitKey, []);
        $maxKb = max(1, (int) ($limitConfig['max_kb'] ?? $defaultKb));
        $maxWidth = max(1, (int) ($limitConfig['max_width'] ?? $defaultWidth));
        $maxHeight = max(1, (int) ($limitConfig['max_height'] ?? $defaultHeight));

        return [
            'max_kb' => $maxKb,
            'max_width' => $maxWidth,
            'max_height' => $maxHeight,
            'max_mb' => (int) ceil($maxKb / 1024),
        ];
    }
}
