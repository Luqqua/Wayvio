<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use App\Services\Meta\CustomMetaPolicyService;
use App\Services\Meta\MetaDefaultsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Tiers\Services\TierResolver;

class MetaController extends Controller
{
    public function __construct(
        private readonly AgencyHubContext $agencyContext,
        private readonly CustomMetaPolicyService $customMetaPolicy,
        private readonly MetaDefaultsService $metaDefaults,
        private readonly TierResolver $tierResolver,
    )
    {
    }

    public function show(): \Illuminate\Contracts\View\View|RedirectResponse
    {
        /** @var User $owner */
        $owner = Auth::user();
        $targetUserId = $this->agencyContext->editingUserId($owner, request());
        Gate::forUser($owner)->authorize('tenant.access-meta', $targetUserId);
        $user = User::query()->findOrFail($targetUserId);
        $canCustomize = $this->canCustomize($owner);
        $requiredTierLabel = $this->requiredTierLabelForCustomMeta();

        $defaults = $this->defaultMeta($user);
        $storedOverrides = is_array($user->meta_overrides) ? $user->meta_overrides : [];
        $formValues = $this->formValuesForView($user, $canCustomize, $defaults, $storedOverrides);

        return view('studio.meta', [
            'user' => $user,
            'formValues' => $formValues,
            'defaults' => $defaults,
            'canCustomize' => $canCustomize,
            'requiredTierLabel' => $requiredTierLabel,
            'hasStoredOverrides' => !empty($storedOverrides),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        /** @var User $owner */
        $owner = Auth::user();
        if (!$this->canCustomize($owner)) {
            $requiredTierLabel = $this->requiredTierLabelForCustomMeta();

            return redirect()
                ->route('subscription.dashboard')
                ->withErrors("Verfügbar ab {$requiredTierLabel}.");
        }

        $targetUserId = $this->agencyContext->editingUserId($owner, $request);
        Gate::forUser($owner)->authorize('tenant.access-meta', $targetUserId);
        $user = User::query()->findOrFail($targetUserId);

        $validator = Validator::make($request->all(), [
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:300'],
            'keywords' => ['nullable', 'string', 'max:300'],
            'og_title' => ['nullable', 'string', 'max:150'],
            'og_description' => ['nullable', 'string', 'max:300'],
            'robots' => ['nullable', Rule::in(['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'])],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'twitter_card' => ['nullable', Rule::in(['summary', 'summary_large_image'])],
            'og_locale' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $payload = $validator->validated();

        $cleanText = function (?string $value): ?string {
            if ($value === null) {
                return null;
            }
            $trimmed = trim(strip_tags($value));
            return $trimmed === '' ? null : $trimmed;
        };

        $overrides = [
            'title' => $cleanText($payload['title'] ?? null),
            'description' => $cleanText($payload['description'] ?? null),
            'keywords' => $cleanText($payload['keywords'] ?? null),
            'og_title' => $cleanText($payload['og_title'] ?? null),
            'og_description' => $cleanText($payload['og_description'] ?? null),
            'robots' => $payload['robots'] ?? null,
            'canonical_url' => $payload['canonical_url'] ?? null,
            'twitter_card' => $payload['twitter_card'] ?? null,
            'og_locale' => $cleanText($payload['og_locale'] ?? null),
        ];

        $overrides = array_filter($overrides, static function ($value) {
            return $value !== null && $value !== '';
        });

        $user->meta_overrides = empty($overrides) ? null : $overrides;
        $user->save();

        return redirect()->route('meta.settings')->with('success', 'Meta tags updated.');
    }

    public function saveFavicon(Request $request): RedirectResponse
    {
        /** @var User $owner */
        $owner = Auth::user();
        if (!$this->canCustomize($owner)) {
            $requiredTierLabel = $this->requiredTierLabelForCustomMeta();

            return redirect()
                ->route('subscription.dashboard')
                ->withErrors("Verfügbar ab {$requiredTierLabel}.");
        }

        $targetUserId = $this->agencyContext->editingUserId($owner, $request);
        Gate::forUser($owner)->authorize('tenant.access-meta', $targetUserId);

        $limit = $this->faviconUploadLimit();
        $validator = Validator::make($request->all(), [
            'favicon' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:' . $limit['max_kb'],
                'dimensions:max_width=' . $limit['max_width'] . ',max_height=' . $limit['max_height'],
            ],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('favicon');
        $mediaStorage = app(\App\Services\Uploads\MediaStorageService::class);

        $oldPath = \App\Models\UserData::getData($targetUserId, $mediaStorage->faviconDataKey());
        $oldPath = is_string($oldPath) && trim($oldPath) !== '' ? $oldPath : null;

        try {
            $key = $mediaStorage->storeUploadedFile($targetUserId, $file, 'favicons');
            \App\Models\UserData::saveData($targetUserId, $mediaStorage->faviconDataKey(), $key);

            if ($oldPath !== null && $oldPath !== $key) {
                $mediaStorage->delete($oldPath);
            }
        } catch (\Throwable $e) {
            Log::error('Favicon save failed', [
                'user_id' => (int) $owner->id,
                'target_user_id' => $targetUserId,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->withErrors(['favicon' => 'Favicon could not be saved. Please try again.']);
        }

        return redirect()->route('meta.settings')->with('success', 'Favicon updated.');
    }

    public function deleteFavicon(Request $request): RedirectResponse
    {
        /** @var User $owner */
        $owner = Auth::user();
        if (!$this->canCustomize($owner)) {
            return redirect()->route('subscription.dashboard');
        }

        $targetUserId = $this->agencyContext->editingUserId($owner, $request);
        Gate::forUser($owner)->authorize('tenant.access-meta', $targetUserId);

        $mediaStorage = app(\App\Services\Uploads\MediaStorageService::class);
        $mediaStorage->deleteFaviconForUser($targetUserId);

        return redirect()->route('meta.settings')->with('success', 'Favicon removed.');
    }

    private function canCustomize(User $owner): bool
    {
        return $this->customMetaPolicy->isFeatureIncludedByTier($owner);
    }

    private function requiredTierLabelForCustomMeta(): string
    {
        $tierOrder = config('tiers.order', ['free', 'basic', 'pro', 'agency']);

        foreach ($tierOrder as $slug) {
            $plan = $this->tierResolver->configForSlug((string) $slug);
            if ((bool) Arr::get($plan, 'features.seo.custom_meta', false)) {
                return (string) ($plan['name'] ?? ucfirst((string) $slug));
            }
        }

        return 'Pro';
    }

    /**
    * @return array{title:string,description:string,keywords:string,robots:string,twitter_card:string,og_locale:string}
    */
    private function defaultMeta(User $user): array
    {
        $displayName = $user->name ?: $user->littlelink_name;

        return $this->metaDefaults->defaultsForPage(
            $user,
            $displayName,
            (string) ($user->littlelink_description ?? '')
        );
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $storedOverrides
     * @return array<string, string>
     */
    private function formValuesForView(User $user, bool $canCustomize, array $defaults, array $storedOverrides): array
    {
        if (!$canCustomize) {
            return [
                'title' => (string) ($defaults['title'] ?? ''),
                'description' => (string) ($defaults['description'] ?? ''),
                'keywords' => (string) ($defaults['keywords'] ?? ''),
                'og_title' => (string) ($defaults['title'] ?? ''),
                'og_description' => (string) ($defaults['description'] ?? ''),
                'robots' => (string) ($defaults['robots'] ?? 'index,follow'),
                'canonical_url' => $this->defaultCanonicalUrl($user),
                'twitter_card' => (string) ($defaults['twitter_card'] ?? 'summary_large_image'),
                'og_locale' => (string) ($defaults['og_locale'] ?? app()->getLocale()),
            ];
        }

        $keys = [
            'title',
            'description',
            'keywords',
            'og_title',
            'og_description',
            'robots',
            'canonical_url',
            'twitter_card',
            'og_locale',
        ];

        $values = [];
        foreach ($keys as $key) {
            $value = $storedOverrides[$key] ?? '';
            $values[$key] = is_scalar($value) ? trim((string) $value) : '';
        }

        return $values;
    }

    private function faviconUploadLimit(): array
    {
        $limitConfig = config('media.upload_limits.favicon', []);
        $maxKb = max(1, (int) ($limitConfig['max_kb'] ?? 512));
        $maxWidth = max(1, (int) ($limitConfig['max_width'] ?? 256));
        $maxHeight = max(1, (int) ($limitConfig['max_height'] ?? 256));

        return [
            'max_kb' => $maxKb,
            'max_width' => $maxWidth,
            'max_height' => $maxHeight,
            'max_mb' => (int) ceil($maxKb / 1024),
        ];
    }

    private function defaultCanonicalUrl(User $user): string
    {
        $canonicalBase = trim((string) config('meta.defaults.canonical_base', ''));
        if ($canonicalBase === '') {
            return '';
        }

        $littlelinkName = ltrim(trim((string) $user->littlelink_name), '@');
        if ($littlelinkName === '') {
            return '';
        }

        return rtrim($canonicalBase, '/') . '/@' . $littlelinkName;
    }
}
