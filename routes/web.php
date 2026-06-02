<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\LinkTypeViewController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\AnalyticsDashboardController;
use App\Http\Controllers\AgencyHubController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\FormsController;
use App\Http\Controllers\FormDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Serve theme assets from /themes even when the web root is /public
Route::get('/themes/{path}', function ($path) {
    $themeRoot = realpath(base_path('themes'));
    $fullPath = $themeRoot ? realpath($themeRoot . DIRECTORY_SEPARATOR . $path) : false;
    $normalizedRoot = $themeRoot ? rtrim($themeRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : null;

    if (
        !$normalizedRoot ||
        !$fullPath ||
        strpos($fullPath, $normalizedRoot) !== 0 ||
        !is_file($fullPath)
    ) {
        abort(404);
    }

    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimeMap = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'svg' => 'image/svg+xml',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    $mimeType = $mimeMap[$extension] ?? File::mimeType($fullPath) ?: 'application/octet-stream';

    return response()->file($fullPath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('path', '.*');

// Prevents section below from being run by 'composer update'
if(file_exists(base_path('storage/app/ISINSTALLED'))){
  // generates new APP KEY if no one is set
  if(EnvEditor::getKey('APP_KEY')==''){try{Artisan::call('key:generate');} catch (exception $e) {}}
 
  // copies template meta config if none is present
  if(!file_exists(base_path("config/advanced-config.php"))){copy(base_path('storage/templates/advanced-config.php'), base_path('config/advanced-config.php'));}
 }

// Installer
if (file_exists(base_path('INSTALLING')) || file_exists(base_path('INSTALLERLOCK'))) {
    Route::middleware(['installer.request'])->group(function (): void {
        Route::get('/', [InstallerController::class, 'showInstaller'])->name('showInstaller');
        Route::post('/create-admin', [InstallerController::class, 'createAdmin'])->name('createAdmin');
        Route::post('/db', [InstallerController::class, 'db'])->name('db');
        Route::post('/mysql', [InstallerController::class, 'mysql'])->name('mysql');
        Route::post('/options', [InstallerController::class, 'options'])->name('options');
        Route::get('/mysql-test', [InstallerController::class, 'mysqlTest'])->name('mysqlTest');
        Route::post('/editConfigInstaller', [InstallerController::class, 'editConfigInstaller'])->name('editConfigInstaller');

        Route::get('{any}', function () {
            if (!DB::table('users')->get()->isEmpty()) {
                if (file_exists(base_path("INSTALLING")) && !file_exists(base_path('INSTALLERLOCK'))) {
                    unlink(base_path("INSTALLING"));
                    header("Refresh:0");
                }
            } else {
                return redirect(url(''));
            }
        })->where('any', '.*');
    });

}else{

// Disables routes if in Maintenance Mode
if(env('MAINTENANCE_MODE') != 'true'){

require __DIR__.'/home.php';

//Redirect if no page URL is set
Route::get('/@', function () {
    return redirect('/studio/no_page_name');
});


// MODULE: Public pricing page to start Stripe checkout
Route::get('/pricing', function () {
    if (request()->filled('ref')) {
        app(\Modules\Partners\Services\PartnerManager::class)
            ->captureReferralCode(request(), (string) request()->query('ref'));
    }

    $resolver = app(\Modules\Tiers\Services\TierResolver::class);
    $planConfig = $resolver->plans();
    $tierModels = \Modules\Tiers\Models\Tier::whereIn('slug', $planConfig->keys())->get()->keyBy('slug');
    $tiers = $planConfig
        ->sortBy('price_1m')
        ->map(function ($tier) use ($tierModels) {
            $model = $tierModels[$tier['slug']] ?? null;
            $limits = $tier['limits'] ?? [];
            $features = $tier['features'] ?? [];

            return (object) array_merge($tier, [
                'id' => $model?->id,
                'max_pages' => $limits['max_pages'] ?? 1,
                'max_links_per_page' => $limits['max_links_per_page'] ?? 10,
                'analytics_history_days' => $limits['analytics_history_days'] ?? Arr::get($features, 'analytics.history_days'),
                'analytics_enabled' => (bool) Arr::get($features, 'analytics.enabled', false),
                'custom_domain_enabled' => (bool) Arr::get($features, 'domains.custom_domain', false),
                'design_customization_enabled' => (bool) Arr::get($features, 'design.link_styling', false)
                    || (bool) Arr::get($features, 'design.custom_colors', false)
                    || (bool) Arr::get($features, 'design.header_image', false)
                    || (bool) Arr::get($features, 'design.background_image', false),
            ]);
        })
        ->values();

    return view('pricing', ['tiers' => $tiers]);
});

// Public Help Center pages
Route::middleware('disableCookies')
    ->prefix('help')
    ->name('help.')
    ->group(function () {
        Route::view('/', 'help.index')->name('index');
        Route::view('/faq', 'help.faq')->name('faq');
        Route::view('/quick-start/user', 'help.quickstart-user')->name('quickstart.user');
        Route::view('/quick-start/agency', 'help.quickstart-agency')->name('quickstart.agency');
        Route::view('/embed-blocks', 'help.embed-blocks')->name('embed-blocks');
        Route::view('/domains', 'help.domains')->name('domains');
        Route::view('/block-editor', 'help.block-editor')->name('block-editor');
        Route::view('/rechtssicherheit-dsgvo', 'help.rechtssicherheit')->name('rechtssicherheit');
        Route::view('/seo-meta', 'help.seo-meta')->name('seo-meta');
        Route::view('/account-abrechnung', 'help.account-abrechnung')->name('account-abrechnung');
        Route::view('/analytics', 'help.analytics')->name('analytics');
        Route::view('/seitenaufbau', 'help.seitenaufbau')->name('seitenaufbau');
        Route::view('/header-modi', 'help.header-modi')->name('header-modi');
        Route::view('/white-label', 'help.white-label')->name('white-label');
    });

// English Help Center
Route::middleware('disableCookies')
    ->prefix('help/en')
    ->name('help.en.')
    ->group(function () {
        Route::view('/', 'help.en.index')->name('index');
        Route::view('/quick-start/user', 'help.en.quickstart-user')->name('quickstart.user');
        Route::view('/quick-start/agency', 'help.en.quickstart-agency')->name('quickstart.agency');
        Route::view('/embed-blocks', 'help.en.embed-blocks')->name('embed-blocks');
        Route::view('/domains', 'help.en.domains')->name('domains');
        Route::view('/block-editor', 'help.en.block-editor')->name('block-editor');
        Route::view('/legal-gdpr', 'help.en.rechtssicherheit')->name('rechtssicherheit');
        Route::view('/seo-meta', 'help.en.seo-meta')->name('seo-meta');
        Route::view('/account-billing', 'help.en.account-abrechnung')->name('account-abrechnung');
        Route::view('/analytics', 'help.en.analytics')->name('analytics');
        Route::view('/page-structure', 'help.en.seitenaufbau')->name('seitenaufbau');
        Route::view('/header-modes', 'help.en.header-modi')->name('header-modi');
        Route::view('/white-label', 'help.en.white-label')->name('white-label');
        Route::view('/faq', 'help.en.faq')->name('faq');
    });

$registerAuthMiddleware = config('auth.register_auth_middleware');
$baseAuthMiddleware = array_values(array_filter([
    'auth',
    is_string($registerAuthMiddleware) ? trim($registerAuthMiddleware) : null,
    'blocked',
], static fn ($middleware) => is_string($middleware) && $middleware !== ''));

// MODULE: CustomDomains direct page routes (core assist)
Route::middleware(array_merge($baseAuthMiddleware, ['tos.pending', 'subscription', 'twofactor']))->group(function () {
    Route::get('/account/custom-domains', function () {
        return view('modules.CustomDomains.views.page');
    })->name('domains.page');

    Route::get('/account/agency-branding', function () {
        return view('modules.CustomDomains.views.page');
    })->name('agency.branding.page');

    Route::get('/account/hub-domain', function () {
        return view('modules.CustomDomains.views.hub-page');
    })->name('domains.hub.page');
});

require __DIR__.'/auth.php';

Route::get('/email/change/{user}', [UserController::class, 'confirmEmailChange'])
    ->middleware(['throttle:profile-email-change-confirm'])
    ->name('profile.email.confirm')
    ->whereNumber('user');

//Public route
$custom_prefix = config('advanced-config.custom_url_prefix');
Route::get('/going/{id?}', [UserController::class, 'clickNumber'])->where('link', '.*')->name('clickNumber')->middleware('disableCookies');
Route::get('/info/{id?}', [AdminController::class, 'redirectInfo'])->name('redirectInfo');
if($custom_prefix != ""){Route::get('/' . $custom_prefix . '{littlelink}', [UserController::class, 'littlelink'])->name('littlelink.prefixed');}
$reservedSlugs = reservedSlugs();
$reservedPattern = implode('|', array_map('preg_quote', $reservedSlugs));

// Keep static legal pages before dynamic public profile routes.
Route::get('/pages/agb', [AdminController::class, 'pagesAgb'])->name('pagesAgb')->middleware('disableCookies');
Route::get('/pages/avv', [AdminController::class, 'pagesAvv'])->name('pagesAvv')->middleware('disableCookies');
$legacyTermsSlug = strtolower(trim((string) footer('Terms')));
if ($legacyTermsSlug !== '' && !in_array($legacyTermsSlug, ['agb', 'avv', 'privacy', 'impressum', 'imprint'], true)) {
    Route::get('/pages/' . $legacyTermsSlug, [AdminController::class, 'pagesTerms'])->name('pagesTerms')->middleware('disableCookies');
}
Route::get('/pages/privacy', [AdminController::class, 'pagesPrivacy'])->name('pagesPrivacy')->middleware('disableCookies');
Route::get('/pages/impressum', [AdminController::class, 'pagesImprint'])->name('pagesImprint')->middleware('disableCookies');
Route::get('/pages/imprint', static function (Request $request) {
    $params = [];
    $legalLang = trim((string) $request->query('legal_lang', ''));
    if ($legalLang !== '') {
        $params['legal_lang'] = $legalLang;
    }

    return redirect()->route('pagesImprint', $params, 301);
})->name('pagesImprint.en')->middleware('disableCookies');
Route::get('/pages/datenschutz', static function (Request $request) {
    $params = [];
    $legalLang = trim((string) $request->query('legal_lang', ''));
    if ($legalLang !== '') {
        $params['legal_lang'] = $legalLang;
    }

    return redirect()->route('pagesPrivacy', $params, 301);
})->name('pagesPrivacy.de')->middleware('disableCookies');
Route::get('/pages/datenschutzerklaerung', static function (Request $request) {
    $params = [];
    $legalLang = trim((string) $request->query('legal_lang', ''));
    if ($legalLang !== '') {
        $params['legal_lang'] = $legalLang;
    }

    return redirect()->route('pagesPrivacy', $params, 301);
})->name('pagesPrivacy.de.long')->middleware('disableCookies');

$legacyPrivacySlug = strtolower((string) footer('Privacy'));
if (
    $legacyPrivacySlug !== ''
    && !in_array($legacyPrivacySlug, ['privacy', 'datenschutz', 'datenschutzerklaerung'], true)
) {
    Route::get('/pages/' . $legacyPrivacySlug, static function (Request $request) {
        $params = [];
        $legalLang = trim((string) $request->query('legal_lang', ''));
        if ($legalLang !== '') {
            $params['legal_lang'] = $legalLang;
        }

        return redirect()->route('pagesPrivacy', $params, 301);
    })->middleware('disableCookies');
}

Route::get('/pages/'.strtolower(footer('Contact')), [AdminController::class, 'pagesContact'])->name('pagesContact')->middleware('disableCookies');

// MODULE: Safe profile route to avoid reserved slug collisions (e.g., admin)
Route::get('/p/{littlelink}', [UserController::class, 'littlelink'])
  ->name('littlelink.safe')
  ->middleware('disableCookies')
  ->where('littlelink', '[A-Za-z0-9._-]+');

// Imprint page for user profiles
Route::get('/{littlelink}/imprint', [UserController::class, 'imprint'])
  ->name('imprint')
  ->middleware('disableCookies')
  ->where('littlelink', '^(?!(' . $reservedPattern . ')$)([A-Za-z0-9._-]+)$');
Route::get('/{littlelink}/impressum', static function (string $littlelink) {
    return redirect()->route('imprint', ['littlelink' => $littlelink], 301);
})
  ->name('imprint.de')
  ->middleware('disableCookies')
  ->where('littlelink', '^(?!(' . $reservedPattern . ')$)([A-Za-z0-9._-]+)$');
Route::get('/{littlelink}/privacy', [UserController::class, 'privacy'])
  ->name('privacy')
  ->middleware('disableCookies')
  ->where('littlelink', '^(?!(' . $reservedPattern . ')$)([A-Za-z0-9._-]+)$');
Route::get('/{littlelink}/datenschutz', static function (string $littlelink) {
    return redirect()->route('privacy', ['littlelink' => $littlelink], 301);
})
  ->name('privacy.de')
  ->middleware('disableCookies')
  ->where('littlelink', '^(?!(' . $reservedPattern . ')$)([A-Za-z0-9._-]+)$');
Route::get('/{littlelink}/datenschutzerklaerung', static function (string $littlelink) {
    return redirect()->route('privacy', ['littlelink' => $littlelink], 301);
})
  ->name('privacy.de.long')
  ->middleware('disableCookies')
  ->where('littlelink', '^(?!(' . $reservedPattern . ')$)([A-Za-z0-9._-]+)$');

// MODULE: Direct page link without @ prefix, avoid collisions with app paths
Route::get('/{littlelink}', [UserController::class, 'littlelink'])
  ->name('littlelink')
  ->middleware('disableCookies')
  ->where('littlelink', '^(?!(' . $reservedPattern . ')$)([A-Za-z0-9._-]+)$');

Route::get('/theme/{littlelink}', [UserController::class, 'theme'])->name('theme')->where('littlelink', '[A-Za-z0-9._-]+');
Route::get('/vcard/{id?}', [UserController::class, 'vcard'])->name('vcard');
Route::get('/u/{id?}', [UserController::class, 'userRedirect'])->name('userRedirect');

Route::get('/report', [UserController::class, 'showReportForm'])->name('report');
Route::post('/report', [UserController::class, 'report'])->middleware('throttle:report-submissions')->name('report.submit');
Route::post('/forms/{hub}/{formKey}/submit', [FormsController::class, 'submit'])
    ->whereNumber('hub')
    ->where('formKey', '[A-Za-z0-9_]+')
    ->middleware(['signed:relative', 'throttle:forms-submissions'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->name('forms.submit');

Route::get('/demo-page', [App\Http\Controllers\HomeController::class, 'demo'])->name('demo')->middleware('disableCookies');
Route::get('/block-asset/{type}', [LinkTypeViewController::class, 'blockAsset'])
  ->name('block.asset')->where(['type' => '[a-zA-Z0-9_-]+']);

}

Route::middleware(array_merge($baseAuthMiddleware, ['tos.pending', 'subscription', 'twofactor' /* MODULE: SaaS subscription middleware */]))->group(function () {
Route::get('/dashboard/subscription', [\Modules\Billing\Http\Controllers\SubscriptionDashboardController::class, 'show'])->name('subscription.dashboard');
Route::get('/subscriptions', function () {
    return redirect()->route('subscription.dashboard');
})->name('subscriptions');
Route::post('/checkout/session', [\Modules\Payments\Http\Controllers\CheckoutController::class, 'createCheckoutSession'])->name('checkout.session');
Route::post('/billing/change-plan/preview', [\Modules\Payments\Http\Controllers\CheckoutController::class, 'previewSubscriptionPlanChange'])->name('billing.change-plan.preview');
Route::post('/billing/change-plan', [\Modules\Payments\Http\Controllers\CheckoutController::class, 'changeSubscriptionPlan'])->name('billing.change-plan');
Route::post('/billing/checkout/confirm', [\Modules\Payments\Http\Controllers\CheckoutController::class, 'confirmCheckout'])->name('billing.checkout.confirm');
// MODULE: Stripe customer portal session
Route::post('/billing/portal', [\Modules\Payments\Http\Controllers\CheckoutController::class, 'customerPortal'])->name('billing.portal');

//User route
Route::group([
    'middleware' => ['tier.limits' /* MODULE: enforce tier limits */],
], function () {
if(app()->environment('production') && env('FORCE_ROUTE_HTTPS') == 'true'){URL::forceScheme('https');}
if(isset($_COOKIE['LinkCount'])){if($_COOKIE['LinkCount'] == '20'){$LinkPage = 'showLinks20';}elseif($_COOKIE['LinkCount'] == '30'){$LinkPage = 'showLinks30';}elseif($_COOKIE['LinkCount'] == 'all'){$LinkPage = 'showLinksAll';} else {$LinkPage = 'showLinks';}} else {$LinkPage = 'showLinks';} //Shows correct link number
Route::get('/dashboard', [AdminController::class, 'index'])->name('panelIndex');
Route::get('/dashboard/analytics', [AnalyticsDashboardController::class, 'show'])->name('analytics.dashboard');
Route::get('/dashboard/forms', [FormDashboardController::class, 'index'])->name('forms.dashboard');
Route::get('/dashboard/forms/export', [FormDashboardController::class, 'export'])->name('forms.dashboard.export');
Route::patch('/dashboard/forms/submissions/read', [FormDashboardController::class, 'markAllRead'])
    ->name('forms.dashboard.mark-all-read');
Route::patch('/dashboard/forms/submissions/{publicId}/read', [FormDashboardController::class, 'markRead'])
    ->where('publicId', '[A-Za-z0-9-]+')
    ->name('forms.dashboard.mark-read');
Route::delete('/dashboard/forms/submissions/{publicId}', [FormDashboardController::class, 'delete'])
    ->where('publicId', '[A-Za-z0-9-]+')
    ->name('forms.dashboard.delete');
Route::get('/studio/index', function(){return redirect(url('dashboard'));});
Route::get('/studio/add-link', [UserController::class, 'AddUpdateLink'])->name('showButtons');
Route::get('/studio/legal', [UserController::class, 'showLegal'])->name('showLegal');
Route::post('/studio/legal', [UserController::class, 'saveLegal'])->name('saveLegal');
Route::post('/studio/legal/privacy', [UserController::class, 'savePrivacyAllLayers'])->name('savePrivacyAllLayers');
Route::post('/studio/legal/privacy/layer1', [UserController::class, 'savePrivacyLayer1'])->name('saveLegalPrivacyLayer1');
Route::post('/studio/legal/privacy/layer2', [UserController::class, 'savePrivacyLayer2'])->name('saveLegalPrivacyLayer2');
Route::post('/studio/legal/privacy/layer3', [UserController::class, 'savePrivacyLayer3'])->name('saveLegalPrivacyLayer3');
Route::post('/studio/edit-link', [UserController::class, 'saveLink'])->name('addLink');
Route::get('/studio/edit-link/{id}', [UserController::class, 'AddUpdateLink'])->name('showLink')->middleware('link-id');
Route::post('/studio/sort-link', [UserController::class, 'sortLinks'])->name('sortLinks');
Route::get('/studio/links', [UserController::class, $LinkPage])->name($LinkPage);
Route::get('/studio/theme', [UserController::class, 'showTheme'])->name('showTheme');
Route::post('/studio/theme', [UserController::class, 'editTheme'])->name('editTheme');
Route::get('/studio/header', [UserController::class, 'showHeader'])->name('showHeader');
Route::post('/studio/header', [UserController::class, 'editHeader'])->middleware('throttle:uploads')->name('editHeader');
Route::delete('/deleteLink/{id}', [UserController::class, 'deleteLink'])->name('deleteLink')->middleware('link-id')->whereNumber('id');
Route::post('/upLink/{up}/{id}', [UserController::class, 'upLink'])->name('upLink')->middleware('link-id')->where(['up' => 'yes|no'])->whereNumber('id');
Route::post('/studio/edit-link/{id}', [UserController::class, 'editLink'])->name('editLink')->middleware('link-id');
Route::get('/studio/button-editor/{id?}', [UserController::class, 'showCSS'])->name('showCSS')->whereNumber('id');
Route::post('/studio/button-editor/{id?}', [UserController::class, 'editCSS'])->name('editCSS')->whereNumber('id');
Route::get('/studio/page-settings', [UserController::class, 'showPage'])->name('showPageSettings');
Route::post('/studio/validate-handle', [UserController::class, 'validateStudioHandle'])->name('studio.validate-handle');
Route::get('/studio/behavior', [UserController::class, 'showPage'])->name('showBehavior');
Route::get('/studio/page', [UserController::class, 'showPage'])->name('showPage');
Route::get('/studio/no_page_name', [UserController::class, 'showPage'])->name('showPageNoName');
Route::post('/studio/page', [UserController::class, 'editPage'])->middleware('throttle:uploads')->name('editPage');
Route::post('/studio/page/locale', [UserController::class, 'updatePageLocale'])->name('page.locale');
Route::post('/studio/page/publication', [UserController::class, 'setPublication'])->name('page.publication');
Route::post('/studio/background', [UserController::class, 'themeBackground'])->middleware('throttle:uploads')->name('themeBackground');
Route::post('/studio/rem-background', [UserController::class, 'removeBackground'])->name('removeBackground');
Route::get('/studio/profile', [UserController::class, 'showProfile'])->name('showProfile');
Route::post('/studio/profile', [UserController::class, 'editProfile'])->middleware('throttle:uploads')->name('editProfile');
Route::post('/studio/profile/locale', [UserController::class, 'updateLocale'])->name('profile.locale');
Route::post('/studio/profile/password', [UserController::class, 'updatePassword'])->middleware('throttle:profile-password-update')->name('profile.password');
Route::post('/studio/profile/email', [UserController::class, 'requestEmailChange'])->middleware('throttle:profile-email-change-request')->name('profile.email');
Route::get('/dashboard/hubs', [AgencyHubController::class, 'index'])->name('agency.hubs.index');
Route::post('/agency/hubs', [AgencyHubController::class, 'store'])->name('agency.hubs.store');
Route::post('/agency/hubs/switch', [AgencyHubController::class, 'switch'])->name('agency.hubs.switch');
Route::post('/agency/hubs/prune', [AgencyHubController::class, 'prune'])->name('agency.hubs.prune');
Route::delete('/agency/hubs/{managed_user_id}', [AgencyHubController::class, 'destroy'])
    ->whereNumber('managed_user_id')
    ->name('agency.hubs.destroy');
Route::post('/agency/hubs/{managed_user_id}/reactivate', [AgencyHubController::class, 'reactivate'])
    ->whereNumber('managed_user_id')
    ->name('agency.hubs.reactivate');
Route::post('/agency/hubs/{managed_user_id}/publication', [AgencyHubController::class, 'setPublication'])
    ->whereNumber('managed_user_id')
    ->name('agency.hubs.publication');
Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable')->middleware('throttle:two-factor-enable');
Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm')->middleware('throttle:two-factor-confirm');
Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable')->middleware('throttle:two-factor-disable');
Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery')->middleware('throttle:two-factor-recovery-codes');
Route::post('/edit-icons', [UserController::class, 'editIcons'])->name('editIcons');
Route::get('/studio/meta', [MetaController::class, 'show'])->name('meta.settings');
Route::post('/studio/meta', [MetaController::class, 'save'])->name('meta.save');
Route::post('/studio/meta/favicon', [MetaController::class, 'saveFavicon'])->middleware('throttle:uploads')->name('meta.favicon.save');
Route::delete('/studio/meta/favicon', [MetaController::class, 'deleteFavicon'])->name('meta.favicon.delete');
Route::post('/clearIcon/{id}', [UserController::class, 'clearIcon'])->name('clearIcon')->whereNumber('id');
Route::delete('/studio/page/delprofilepicture', [UserController::class, 'delProfilePicture'])->name('delProfilePicture');
Route::delete('/studio/delete-user/{id}', [UserController::class, 'deleteUser'])->name('deleteUser')->middleware(['verified', 'throttle:profile-delete-user'])->whereNumber('id');
// Catch all redirects
Route::get('/admin/users/all', fn() => redirect(route('showUsers')));
Route::get('/studio', fn() => redirect(url('dashboard')));
Route::get('/studio/edit-link', fn() => redirect(url('dashboard')));

if(env('ALLOW_USER_EXPORT') != false){
  Route::get('/export-links', [UserController::class, 'exportLinks'])->name('exportLinks');
  Route::get('/export-all', [UserController::class, 'exportAll'])->name('exportAll');
}
Route::get('/studio/linkparamform_part/{typeid}/{linkid}', [LinkTypeViewController::class, 'getParamForm'])->name('linkparamform.part');
});
});
}

//Social login route
Route::get('/social-auth/{provider}/callback', [SocialLoginController::class, 'providerCallback']);
Route::get('/social-auth/{provider}', [SocialLoginController::class, 'redirectToProvider'])->name('social.redirect');

// Stripe webhook endpoint
Route::post('/stripe/webhook', [\Modules\Payments\Http\Controllers\CheckoutController::class, 'handleWebhook'])
    ->middleware('throttle:stripe-webhook')
    ->name('stripe.webhook');

Route::middleware(array_merge($baseAuthMiddleware, ['twofactor']))->group(function () {
//Admin route
Route::group([
    'middleware' => 'admin',
], function () {
    if(app()->environment('production') && env('FORCE_ROUTE_HTTPS') == 'true'){URL::forceScheme('https');}
    Route::get('/panel/index', function(){return redirect(url('dashboard'));});
    Route::get('/panel/diagnose', function () { return view('panel/diagnose', []); });
    Route::get('/admin/users', [AdminController::class, 'users'])->name('showUsers');

}); // End Admin authenticated routes
});

// Displays Maintenance Mode page
if(env('MAINTENANCE_MODE') == 'true'){
Route::get('/{any}', function () {
  return view('maintenance');
  })->where('any', '.*');
}
