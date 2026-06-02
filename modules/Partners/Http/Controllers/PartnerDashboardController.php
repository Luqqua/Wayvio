<?php

namespace Modules\Partners\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Partners\PartnersClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Partners\Services\PartnerDashboardService;
use Modules\Partners\Services\PartnerManager;

class PartnerDashboardController extends Controller
{
    public function __construct(
        private readonly PartnerManager $partnerManager,
        private readonly PartnerDashboardService $dashboardService,
        private readonly PartnersClient $partnersClient,
    ) {
    }

    public function show(Request $request)
    {
        $this->ensureWebDashboardEnabled();
        $this->partnerManager->validateDashboardPartnerAccess($request->user());

        $payload = null;
        if ($this->partnersClient->enabled()) {
            $payload = $this->partnersClient->dashboard((int) $request->user()->id, (int) $request->user()->id);
        }

        if (!is_array($payload)) {
            $payload = $this->dashboardService->dashboardPayload($request->user());
        }

        $partner = $this->partnerManager->dashboardAccountFor($request->user());
        $allowedCountries = $this->allowedOnboardingCountries();
        $storedLegalCountry = strtoupper(trim((string) ($partner?->legal_country ?? '')));

        $payloadPartner = is_array($payload['partner'] ?? null) ? $payload['partner'] : [];
        $payloadLegalCountry = strtoupper(trim((string) ($payloadPartner['legal_country'] ?? '')));
        $selectedLegalCountry = $payloadLegalCountry !== ''
            ? $payloadLegalCountry
            : ($storedLegalCountry !== '' ? $storedLegalCountry : null);

        $countryLocked = !empty($partner?->stripe_connect_account_id);
        $payloadPartner['legal_country'] = $selectedLegalCountry;
        $payloadPartner['legal_country_locked'] = $countryLocked;
        $payload['partner'] = $payloadPartner;
        $payload['connect_country'] = [
            'allowed' => $allowedCountries,
            'selected' => $selectedLegalCountry,
            'locked' => $countryLocked,
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return view('modules.Partners.views.dashboard', $payload);
    }

    public function createInviteCode(Request $request): JsonResponse
    {
        $this->ensureWebDashboardEnabled();
        $partner = $this->partnerManager->validateDashboardPartnerAccess($request->user());
        $code = $this->partnerManager->ensureDefaultInviteCode($partner);

        return response()->json([
            'id' => (int) $code->id,
            'code' => $code->code,
            'referral_url' => url('/ref/' . $code->code),
        ]);
    }

    public function startOnboarding(Request $request): JsonResponse
    {
        $this->ensureWebDashboardEnabled();
        $this->partnerManager->validateDashboardPartnerAccess($request->user());
        $partner = $this->partnerManager->dashboardAccountFor($request->user());

        if (!$this->partnersClient->enabled()) {
            return response()->json([
                'error' => 'partners_api_disabled',
                'message' => 'Partner API is disabled.',
            ], 503);
        }

        if (!$partner) {
            return response()->json([
                'error' => 'partner_not_found',
                'message' => 'Partner account not found.',
            ], 404);
        }

        $allowedCountries = $this->allowedOnboardingCountries();
        $validated = $request->validate([
            'country' => ['nullable', 'string', 'size:2', Rule::in($allowedCountries)],
        ]);

        $country = strtoupper(trim((string) ($validated['country'] ?? '')));
        $storedCountry = strtoupper(trim((string) ($partner->legal_country ?? '')));
        $hasConnectAccount = !empty($partner->stripe_connect_account_id);

        if ($storedCountry !== '' && $country === '') {
            $country = $storedCountry;
        }

        if ($hasConnectAccount && $storedCountry !== '' && $country !== '' && $country !== $storedCountry) {
            return response()->json([
                'error' => 'partner_country_locked',
                'message' => 'Country cannot be changed after Stripe onboarding has started.',
            ], 422);
        }

        if (!$hasConnectAccount && $country === '') {
            return response()->json([
                'error' => 'partner_country_required',
                'message' => 'Please select your legal business country before starting onboarding.',
                'allowed_countries' => $allowedCountries,
            ], 422);
        }

        if ($country !== '' && $storedCountry !== $country) {
            $partner->legal_country = $country;
            $partner->save();
        }

        $result = $this->partnersClient->startOnboarding(
            (int) $request->user()->id,
            (string) $request->user()->email,
            $country !== '' ? $country : null,
            (int) $request->user()->id,
        );

        $onboardingUrl = is_array($result) ? data_get($result, 'onboarding_url') : null;
        if (!is_string($onboardingUrl) || !Str::startsWith($onboardingUrl, ['http://', 'https://'])) {
            return response()->json([
                'error' => 'partners_onboarding_unavailable',
                'message' => 'Unable to start Stripe onboarding right now.',
            ], 503);
        }

        return response()->json([
            'onboarding_url' => $onboardingUrl,
        ]);
    }

    /**
     * @return array<int,string>
     */
    private function allowedOnboardingCountries(): array
    {
        $raw = config('internal-api.partners.allowed_countries', ['DE', 'AT']);
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        if (!is_array($raw)) {
            return ['DE', 'AT'];
        }

        $normalized = [];
        foreach ($raw as $country) {
            $value = strtoupper(trim((string) $country));
            if (preg_match('/^[A-Z]{2}$/', $value) !== 1) {
                continue;
            }

            if (!in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized !== [] ? $normalized : ['DE', 'AT'];
    }

    public function onboardingStatus(Request $request): JsonResponse
    {
        $this->ensureWebDashboardEnabled();
        $this->partnerManager->validateDashboardPartnerAccess($request->user());

        if (!$this->partnersClient->enabled()) {
            return response()->json([
                'error' => 'partners_api_disabled',
                'message' => 'Partner API is disabled.',
            ], 503);
        }

        $result = $this->partnersClient->onboardingStatus((int) $request->user()->id, (int) $request->user()->id);
        if (!is_array($result)) {
            return response()->json([
                'error' => 'partners_onboarding_status_unavailable',
                'message' => 'Unable to fetch onboarding status.',
            ], 503);
        }

        return response()->json($result);
    }

    private function ensureWebDashboardEnabled(): void
    {
        abort_unless((bool) config('partners.web_dashboard_enabled', false), 404);
    }
}
