<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\App;
use JeroenDesloovere\VCard\VCard;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use GeoSot\EnvEditor\Facades\EnvEditor;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Services\TwoFactorService;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\EmailChangeVerification;
use App\Notifications\EmailChangeRequested;
use App\Notifications\EmailChanged;
use Illuminate\Database\QueryException;

use Auth;
use DB;
use File;

use App\Models\User;
use App\Models\AgencyHub;
use App\Models\Button;
use App\Models\Link;
use App\Models\LinkType;
use App\Models\PageReport;
use App\Models\PageReportEvent;
use App\Models\UserData;
use Modules\Tiers\Services\SubscriptionManager; // MODULE: Expose tier data for profile display
use Modules\Tiers\Models\UserSubscription; // MODULE: Fetch subscription window/expiry for profile
use App\Services\Analytics\AnalyticsDispatcher;
use App\Services\AccountDeletionService;
use App\Services\Agency\AgencyHubContext;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\Domains\DomainUrlResolver;
use App\Services\Exceptions\AccountDeletionBlockedException;
use App\Services\Hubs\HubPublicationService;
use App\Services\Lifecycle\AccountLifecycleService;
use App\Services\Security\CaptchaVerifier;
use App\Services\Templates\TemplateCatalogService;
use App\Services\Tenancy\TenantResolver;
use App\Services\Uploads\MediaStorageService;
use Modules\Tiers\Services\TierResolver;
use Illuminate\Support\Facades\Crypt;


//Function tests if string starts with certain string (used to test for illegal strings)
function stringStartsWith($haystack, $needle, $case = true)
{
    if ($case) {
        return strpos($haystack, $needle, 0) === 0;
    }
    return stripos($haystack, $needle, 0) === 0;
}

//Function tests if string ends with certain string (used to test for illegal strings)
function stringEndsWith($haystack, $needle, $case = true)
{
    $expectedPosition = strlen($haystack) - strlen($needle);
    if ($case) {
        return strrpos($haystack, $needle, 0) === $expectedPosition;
    }
    return strripos($haystack, $needle, 0) === $expectedPosition;
}

class UserController extends Controller
{

    //Legacy dashboard entrypoint (kept for compatibility)
    public function index()
    {
        $userId = $this->activeEditorUserId();
        $userinfo = User::find($userId);
        $littlelink_name = (string) ($userinfo?->littlelink_name ?? Auth::user()->littlelink_name);

        $links = Link::where('user_id', $userId)->select('link')->count();
        // Legacy per-link click counters were removed in favor of internal API analytics.
        $clicks = 0;
        $topLinks = collect();

        $pageStats = [
            'visitors' => [
                'all' => visits('App\Models\User', $littlelink_name)->count(),
                'day' => visits('App\Models\User', $littlelink_name)->period('day')->count(),
                'week' => visits('App\Models\User', $littlelink_name)->period('week')->count(),
                'month' => visits('App\Models\User', $littlelink_name)->period('month')->count(),
                'year' => visits('App\Models\User', $littlelink_name)->period('year')->count(),
            ],
            'os' => visits('App\Models\User', $littlelink_name)->operatingSystems(),
            'referers' => visits('App\Models\User', $littlelink_name)->refs(),
            'countries' => visits('App\Models\User', $littlelink_name)->countries(),
        ];



        return view('studio/index', ['greeting' => $userinfo->name, 'toplinks' => $topLinks, 'links' => $links, 'clicks' => $clicks, 'pageStats' => $pageStats]);
    }

    //Show littlelink page. example => http://127.0.0.1:8000/+admin
    public function littlelink(request $request)
    {
        $slug = $request->littlelink;
        if (!in_array($slug, reservedSlugs()) && $request->is('p/*')) {
            return redirect('/' . $slug);
        }

        if(isset($request->useif)){
            $littlelink_name = User::select('littlelink_name')->where('id', $request->littlelink)->value('littlelink_name');
            $id = $request->littlelink;
        } else {
            $littlelink_name = $request->littlelink;
            $id = User::select('id')->where('littlelink_name', $littlelink_name)->value('id');
        }

        if (empty($id)) {
            return abort(404);
        }

        if (app(AccountLifecycleService::class)->publicPageUnavailableReason((int) $id) !== null) {
            return response()->view('wayvio.unavailable', [], 200);
        }
     
        $userinfo = $this->publicPageUserQuery()->where('id', $id)->first();
        if (!$userinfo) {
            return abort(404);
        }
        $information = User::select('name', 'littlelink_name', 'littlelink_description', 'theme', 'meta_overrides')->where('id', $id)->get();
        
        if ($userinfo->block == 'yes') {
            return abort(404);
        }

        if (!$this->canRenderPublishedHub($userinfo, $request->user())) {
            return $this->renderUnpublishedPageNotAvailable();
        }
        
        $links = DB::table('links')
        ->join('buttons', 'buttons.id', '=', 'links.button_id')
        ->select('links.*', 'buttons.name') // Assuming 'links.*' to fetch all columns including 'type_params'
        ->where('user_id', $id)
        ->when(Schema::hasColumn('links', 'is_disabled'), function ($q) {
            $q->where('links.is_disabled', false);
        })
        ->orderBy('up_link', 'asc')
        ->orderBy('order', 'asc')
        ->get();

        // Loop through each link to decode 'type_params' and merge it into the link object
        foreach ($links as $link) {
            if (!empty($link->type_params)) {
                // Decode the JSON string into an associative array
                $typeParams = json_decode($link->type_params, true);
                if (is_array($typeParams)) {
                    // Merge the associative array into the link object
                    foreach ($typeParams as $key => $value) {
                        $link->$key = $value;
                    }
                }
            }
        }

        // Server-side analytics capture for profile views (tier gated)
        app(AnalyticsDispatcher::class)->recordPageView($request, $userinfo, [
            'page_slug' => $littlelink_name,
            'page_id' => $id,
        ]);

        return view('wayvio.wayvio', ['userinfo' => $userinfo, 'information' => $information, 'links' => $links, 'littlelink_name' => $littlelink_name]);
    }

    public function imprint(Request $request)
    {
        $slug = $request->littlelink;
        if (in_array($slug, reservedSlugs())) {
            return abort(404);
        }

        $id = User::select('id')->where('littlelink_name', $slug)->value('id');
        if (empty($id)) {
            return abort(404);
        }

        if (app(AccountLifecycleService::class)->publicPageUnavailableReason((int) $id) !== null) {
            return response()->view('wayvio.unavailable', [], 200);
        }

        $userinfo = $this->publicPageUserQuery()
            ->where('id', $id)
            ->first();
        if (!$userinfo) {
            return abort(404);
        }
        $information = User::select('name', 'littlelink_name', 'littlelink_description', 'theme', 'meta_overrides')
            ->where('id', $id)
            ->get();

        if ($userinfo->block == 'yes') {
            return abort(404);
        }

        if (!$this->canRenderPublishedHub($userinfo, $request->user())) {
            return $this->renderUnpublishedPageNotAvailable();
        }

        // Public imprint language follows the page owner's explicit account language.
        // We intentionally do not use last_login_locale here to avoid stale browser-language overrides.
        App::setLocale($this->resolvePublicPageLocale($userinfo));

        $imprintQuery = Link::where('user_id', $id)->where('type', 'imprint');
        if (Schema::hasColumn('links', 'is_disabled')) {
            $imprintQuery->where('is_disabled', false);
        }
        $imprintLink = $imprintQuery->orderBy('order', 'asc')->orderBy('id', 'asc')->first();
        if (!$imprintLink) {
            return abort(404);
        }

        $imprintParams = [];
        if (!empty($imprintLink->type_params)) {
            $decoded = json_decode($imprintLink->type_params, true);
            if (is_array($decoded)) {
                $imprintParams = $decoded;
            }
        }

        $storedImprintTitle = trim((string) ($imprintLink->title ?? ''));
        $normalizedImprintTitle = function_exists('mb_strtolower')
            ? mb_strtolower($storedImprintTitle, 'UTF-8')
            : strtolower($storedImprintTitle);

        $defaultTitleCandidates = [
            'impressum',
            'imprint',
            'legal notice',
        ];

        $imprintDisplayTitle = ($storedImprintTitle === '' || in_array($normalizedImprintTitle, $defaultTitleCandidates, true))
            ? __('messages.imprint.default_title')
            : $storedImprintTitle;

        return view('wayvio.imprint', [
            'userinfo' => $userinfo,
            'information' => $information,
            'littlelink_name' => $slug,
            'imprint_title' => $imprintDisplayTitle,
            'imprint_name' => $imprintParams['imprint_name'] ?? '',
            'imprint_legal_form' => $imprintParams['imprint_legal_form'] ?? '',
            'imprint_represented_by' => $imprintParams['imprint_represented_by'] ?? '',
            'imprint_street' => $imprintParams['imprint_street'] ?? '',
            'imprint_postal_code' => $imprintParams['imprint_postal_code'] ?? '',
            'imprint_city' => $imprintParams['imprint_city'] ?? '',
            'imprint_country' => $imprintParams['imprint_country'] ?? '',
            'imprint_email' => $imprintParams['imprint_email'] ?? '',
            'imprint_phone' => $imprintParams['imprint_phone'] ?? '',
            'imprint_fax' => $imprintParams['imprint_fax'] ?? '',
            'imprint_supervisory_authority' => $imprintParams['imprint_supervisory_authority'] ?? '',
            'imprint_register_name' => $imprintParams['imprint_register_name'] ?? '',
            'imprint_register_court' => $imprintParams['imprint_register_court'] ?? '',
            'imprint_register_number' => $imprintParams['imprint_register_number'] ?? '',
            'imprint_professional_title' => $imprintParams['imprint_professional_title'] ?? '',
            'imprint_professional_state' => $imprintParams['imprint_professional_state'] ?? '',
            'imprint_chamber' => $imprintParams['imprint_chamber'] ?? '',
            'imprint_professional_rules' => $imprintParams['imprint_professional_rules'] ?? '',
            'imprint_vat_id' => $imprintParams['imprint_vat_id'] ?? '',
            'imprint_business_id' => $imprintParams['imprint_business_id'] ?? '',
            'imprint_liquidation_notice' => $imprintParams['imprint_liquidation_notice'] ?? '',
            'imprint_adr_notice' => $imprintParams['imprint_adr_notice'] ?? '',
            'imprint_odr_url' => $imprintParams['imprint_odr_url'] ?? '',
            'imprint_av_member_state' => $imprintParams['imprint_av_member_state'] ?? '',
            'imprint_av_authority' => $imprintParams['imprint_av_authority'] ?? '',
            'imprint_additional_text' => $imprintParams['imprint_additional_text'] ?? '',
            'imprint_contact_form_enabled' => filter_var($imprintParams['imprint_contact_form_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function privacy(Request $request)
    {
        $slug = $request->littlelink;
        if (in_array($slug, reservedSlugs(), true)) {
            return abort(404);
        }

        $id = User::select('id')->where('littlelink_name', $slug)->value('id');
        if (empty($id)) {
            return abort(404);
        }

        if (app(AccountLifecycleService::class)->publicPageUnavailableReason((int) $id) !== null) {
            return response()->view('wayvio.unavailable', [], 200);
        }

        $userinfo = $this->publicPageUserQuery()
            ->where('id', $id)
            ->first();
        if (!$userinfo) {
            return abort(404);
        }
        $information = User::select('name', 'littlelink_name', 'littlelink_description', 'theme', 'meta_overrides')
            ->where('id', $id)
            ->get();

        if ($userinfo->block == 'yes') {
            return abort(404);
        }

        if (!$this->canRenderPublishedHub($userinfo, $request->user())) {
            return $this->renderUnpublishedPageNotAvailable();
        }

        $privacyLocale = $this->privacyLocaleForUserId((int) $id);
        App::setLocale($privacyLocale);

        $imprintLink = $this->firstImprintLinkForUser((int) $id);
        $imprintParams = $this->decodedTypeParams($imprintLink?->type_params);
        $privacyLayerModel = $this->privacyLayerModelForUser((int) $id, $imprintParams, $privacyLocale);
        $privacyText = $this->renderPrivacyNoticeOutput($privacyLayerModel, $privacyLocale);
        $privacyTitle = $privacyLocale === 'en' ? 'Privacy Policy' : 'Datenschutzerklärung';

        return view('wayvio.privacy', [
            'userinfo' => $userinfo,
            'information' => $information,
            'littlelink_name' => $slug,
            'privacy_title' => $privacyTitle,
            'privacy_text' => $privacyText,
        ]);
    }

    //Show littlelink page as home page if set in config
    public function littlelinkhome(request $request)
    {
        $littlelink_name = env('HOME_URL');
        $id = User::select('id')->where('littlelink_name', $littlelink_name)->value('id');

        if (empty($id)) {
            return abort(404);
        }

        if (app(AccountLifecycleService::class)->publicPageUnavailableReason((int) $id) !== null) {
            return response()->view('wayvio.unavailable', [], 200);
        }
     
        $userinfo = $this->publicPageUserQuery()->where('id', $id)->first();
        if (!$userinfo) {
            return abort(404);
        }
        $information = User::select('name', 'littlelink_name', 'littlelink_description', 'theme', 'meta_overrides')->where('id', $id)->get();

        if ($userinfo->block == 'yes') {
            return abort(404);
        }

        if (!$this->canRenderPublishedHub($userinfo, $request->user())) {
            return $this->renderUnpublishedPageNotAvailable();
        }
        
        $links = DB::table('links')
        ->join('buttons', 'buttons.id', '=', 'links.button_id')
        ->select('links.*', 'buttons.name') // Assuming 'links.*' to fetch all columns including 'type_params'
        ->where('user_id', $id)
        ->when(Schema::hasColumn('links', 'is_disabled'), function ($q) {
            $q->where('links.is_disabled', false);
        })
        ->orderBy('up_link', 'asc')
        ->orderBy('order', 'asc')
        ->get();

        // Loop through each link to decode 'type_params' and merge it into the link object
        foreach ($links as $link) {
            if (!empty($link->type_params)) {
                // Decode the JSON string into an associative array
                $typeParams = json_decode($link->type_params, true);
                if (is_array($typeParams)) {
                    // Merge the associative array into the link object
                    foreach ($typeParams as $key => $value) {
                        $link->$key = $value;
                    }
                }
            }
        }

        app(AnalyticsDispatcher::class)->recordPageView($request, $userinfo, [
            'page_slug' => $littlelink_name,
            'page_id' => $id,
        ]);

        return view('wayvio.wayvio', ['userinfo' => $userinfo, 'information' => $information, 'links' => $links, 'littlelink_name' => $littlelink_name]);
    }

    //Redirect to user page
    public function userRedirect(request $request)
    {
        $id = $request->id;
        $user = User::select('littlelink_name')->where('id', $id)->value('littlelink_name');

        if (empty($id)) {
            return abort(404);
        }
     
        if (empty($user)) {
            return abort(404);
        }

        return redirect(url('@'.$user));
    }

    //Show add/update form
    public function AddUpdateLink(Request $request, $id = 0)
    {
        $activeUserId = $this->activeEditorUserId($request);
        $requestedPageId = $request->filled('page_id') ? (int) $request->query('page_id') : null;
        $this->denyTenantPageContextMismatch(
            request: $request,
            activeResourceUserId: $activeUserId,
            suppliedPageId: $requestedPageId,
            reasonCode: 'tenant_page_context_mismatch_add_update_link',
        );

        $linkData = $id
            ? Link::where('id', $id)->where('user_id', $activeUserId)->firstOrFail()
            : new Link(['typename' => 'link', 'id' => '0']);

        if ((int) $id > 0 && (string) ($linkData->type ?? '') === 'imprint') {
            return redirect()->route('showLegal', ['page_id' => $activeUserId]);
        }

        $linkTypes = LinkType::get()
            ->reject(static fn ($type) => (string) ($type->typename ?? '') === 'imprint')
            ->values();
    
        $data = [
            'LinkTypes' => $linkTypes,
            'LinkData' => $linkData,
            'LinkID' => $id,
            'linkTypeID' => "predefined",
            'title' => "Predefined Site",
            'selectedPageId' => $activeUserId,
        ];

        $defaultTypeName = $id ? ($linkData->type ?? 'predefined') : '';
        $data['typename'] = old('typename', $defaultTypeName);

        return view('studio/edit-link', $data);
    }

    //Show legal/imprint editor
    public function showLegal(Request $request)
    {
        $activeUserId = $this->activeEditorUserId($request);
        $requestedPageId = $request->filled('page_id') ? (int) $request->query('page_id') : null;
        $this->denyTenantPageContextMismatch(
            request: $request,
            activeResourceUserId: $activeUserId,
            suppliedPageId: $requestedPageId,
            reasonCode: 'tenant_page_context_mismatch_show_legal',
        );

        $imprintLink = $this->firstImprintLinkForUser($activeUserId);
        $storedParams = $this->decodedTypeParams($imprintLink?->type_params);

        $formData = [];
        foreach ($this->imprintFieldKeys() as $field) {
            $formData[$field] = old($field, $storedParams[$field] ?? '');
        }

        $privacyLocale = $this->privacyLocaleForUserId($activeUserId);
        App::setLocale($privacyLocale);
        $privacyLayerModel = $this->privacyLayerModelForUser($activeUserId, $storedParams, $privacyLocale);
        $privacyLiveOutput = $this->renderPrivacyNoticeOutput($privacyLayerModel, $privacyLocale);
        $layer2Model = (array) ($privacyLayerModel['layer2'] ?? []);
        $localeTextKey = 'user_privacy_text_' . $privacyLocale;
        $privacyEditableText = trim((string) ($layer2Model[$localeTextKey] ?? ''));
        if ($privacyEditableText === '') {
            $privacyEditableText = trim((string) ($layer2Model['user_privacy_text'] ?? ''));
        }
        $privacyEditableText = $this->stripControllerSectionFromPrivacyText($privacyEditableText, $privacyLocale);

        return view('studio.legal', array_merge($formData, [
            'selectedPageId' => $activeUserId,
            'imprintLinkId' => (int) ($imprintLink?->id ?? 0),
            'privacyLayerModel' => $privacyLayerModel,
            'privacyLiveOutput' => $privacyLiveOutput,
            'privacyEditableText' => $privacyEditableText,
            'privacyLocale' => $privacyLocale,
        ]));
    }

    //Save legal/imprint editor
    public function saveLegal(Request $request)
    {
        $activeEditorUserId = $this->activeEditorUserId($request);
        $editorLocale = $this->privacyLocaleForUserId($activeEditorUserId);
        App::setLocale($editorLocale);
        $submittedPageId = $request->filled('page_id') ? (int) $request->input('page_id') : null;
        $this->denyTenantPageContextMismatch(
            request: $request,
            activeResourceUserId: $activeEditorUserId,
            suppliedPageId: $submittedPageId,
            reasonCode: 'tenant_page_context_mismatch_save_legal',
            message: __('messages.legal.hub_context_changed', [], $editorLocale),
        );

        $linkType = LinkType::findByTypename('imprint');
        if (!$linkType) {
            abort(404, __('messages.legal.editor_unavailable', [], $editorLocale));
        }

        $handlerPath = base_path('blocks/imprint/handler.php');
        if (!file_exists($handlerPath)) {
            abort(404, __('messages.legal.editor_logic_missing', [], $editorLocale));
        }

        require_once $handlerPath;
        $result = $this->executeBlockHandler($request, $linkType);

        $validator = Validator::make($request->all(), $result['rules'] ?? []);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $linkData = (array) ($result['linkData'] ?? []);
        $linkData['type'] = 'imprint';
        $linkData['button_id'] = $linkData['button_id'] ?? 1;
        $linkData['user_id'] = $activeEditorUserId;

        $linkColumns = Schema::getColumnListing('links');
        $filteredLinkData = array_intersect_key($linkData, array_flip($linkColumns));
        $customParams = array_diff_key($linkData, $filteredLinkData);

        if (isset($linkType->custom_html)) {
            $customParams['custom_html'] = $linkType->custom_html;
        }

        if (isset($linkType->ignore_container)) {
            $customParams['ignore_container'] = $linkType->ignore_container;
        }

        if (isset($linkType->include_libraries)) {
            $customParams['include_libraries'] = $linkType->include_libraries;
        }

        $filteredLinkData['type_params'] = json_encode($customParams);

        $imprintLink = $this->firstImprintLinkForUser($activeEditorUserId);
        if ($imprintLink) {
            foreach ($filteredLinkData as $column => $value) {
                $imprintLink->{$column} = $value;
            }
            if (Schema::hasColumn('links', 'is_disabled')) {
                $imprintLink->is_disabled = false;
            }
            $imprintLink->save();
        } else {
            $newImprintLink = new Link($filteredLinkData);
            $newImprintLink->user_id = $activeEditorUserId;
            if (Schema::hasColumn('links', 'order')) {
                $newImprintLink->order = (int) Link::withDisabled()
                    ->where('user_id', $activeEditorUserId)
                    ->max('order') + 1;
            }
            if (Schema::hasColumn('links', 'is_disabled')) {
                $newImprintLink->is_disabled = false;
            }
            $newImprintLink->save();
        }

        return redirect()->route('showLegal', ['page_id' => $activeEditorUserId])->with('success', __('messages.legal.imprint.saved', [], $editorLocale));
    }

    public function savePrivacyLayer1(Request $request)
    {
        $activeEditorUserId = $this->activeEditorUserId($request);
        $editorLocale = $this->privacyLocaleForUserId($activeEditorUserId);
        App::setLocale($editorLocale);
        $submittedPageId = $request->filled('page_id') ? (int) $request->input('page_id') : null;
        $this->denyTenantPageContextMismatch(
            request: $request,
            activeResourceUserId: $activeEditorUserId,
            suppliedPageId: $submittedPageId,
            reasonCode: 'tenant_page_context_mismatch_save_privacy_layer1',
            message: __('messages.legal.hub_context_changed', [], $editorLocale),
        );

        $imprintLink = $this->firstImprintLinkForUser($activeEditorUserId);
        $storedImprintParams = $this->decodedTypeParams($imprintLink?->type_params);
        $privacyLocale = $editorLocale;
        $model = $this->privacyLayerModelForUser($activeEditorUserId, $storedImprintParams, $privacyLocale);
        $imprintLayer1 = $this->privacyLayer1ImprintDefaults($storedImprintParams, $privacyLocale);
        $existingOverrides = $this->normalizePrivacyLayer1(
            (array) ($model['layer1_overrides'] ?? []),
            array_fill_keys($this->privacyLayer1SyncableKeys(), '')
        );
        $submittedSync = [];
        foreach ($this->privacyLayer1SyncableKeys() as $key) {
            $submittedSync[$key] = $request->boolean('privacy_sync_' . $key);
        }

        $submittedOverrides = [
            'controller_name' => trim((string) $request->input('privacy_controller_name')),
            'street' => trim((string) $request->input('privacy_street')),
            'postal_code' => trim((string) $request->input('privacy_postal_code')),
            'city' => trim((string) $request->input('privacy_city')),
            'country' => trim((string) $request->input('privacy_country')),
            'email' => trim((string) $request->input('privacy_email')),
            'phone' => trim((string) $request->input('privacy_phone')),
            'dpo_name' => trim((string) $request->input('privacy_dpo_name')),
            'dpo_contact' => trim((string) $request->input('privacy_dpo_contact')),
        ];

        foreach ($this->privacyLayer1SyncableKeys() as $key) {
            if ($submittedSync[$key]) {
                $submittedOverrides[$key] = trim((string) ($existingOverrides[$key] ?? ''));
            }
        }

        $effectiveLayer1 = $this->applyPrivacyLayer1Sync($imprintLayer1, $submittedOverrides, $submittedSync);
        $effectiveLayer1['dpo_name'] = trim((string) $submittedOverrides['dpo_name']);
        $effectiveLayer1['dpo_contact'] = trim((string) $submittedOverrides['dpo_contact']);

        $effectiveValidator = Validator::make($effectiveLayer1, [
            'controller_name' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:64'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'dpo_name' => ['nullable', 'string', 'max:255'],
            'dpo_contact' => ['nullable', 'string', 'max:500'],
        ]);
        if ($effectiveValidator->fails()) {
            return back()->withErrors($effectiveValidator)->withInput();
        }

        $model['layer1'] = $effectiveLayer1;
        $model['layer1_sync'] = $submittedSync;
        $model['layer1_overrides'] = $this->normalizePrivacyLayer1($submittedOverrides, array_fill_keys($this->privacyLayer1SyncableKeys(), ''));
        $model['layer1_imprint_defaults'] = $imprintLayer1;

        $this->savePrivacyLayerModel($activeEditorUserId, $model);

        return redirect()->route('showLegal', ['page_id' => $activeEditorUserId])
            ->with('success', __('messages.legal.privacy.layer1.saved', [], $editorLocale));
    }

    public function savePrivacyLayer2(Request $request)
    {
        $activeEditorUserId = $this->activeEditorUserId($request);
        $editorLocale = $this->privacyLocaleForUserId($activeEditorUserId);
        App::setLocale($editorLocale);
        $submittedPageId = $request->filled('page_id') ? (int) $request->input('page_id') : null;
        $this->denyTenantPageContextMismatch(
            request: $request,
            activeResourceUserId: $activeEditorUserId,
            suppliedPageId: $submittedPageId,
            reasonCode: 'tenant_page_context_mismatch_save_privacy_layer2',
            message: __('messages.legal.hub_context_changed', [], $editorLocale),
        );

        $action = trim((string) $request->input('layer2_action', 'save'));

        $validator = Validator::make($request->all(), [
            'privacy_user_text' => ['nullable', 'string', 'max:120000'],
            'layer2_action' => ['nullable', 'in:save,regenerate'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $imprintLink = $this->firstImprintLinkForUser($activeEditorUserId);
        $storedImprintParams = $this->decodedTypeParams($imprintLink?->type_params);
        $privacyLocale = $editorLocale;
        $model = $this->privacyLayerModelForUser($activeEditorUserId, $storedImprintParams, $privacyLocale);
        $layer1 = (array) ($model['layer1'] ?? []);

        if ($action === 'regenerate') {
            $model['layer2']['user_privacy_text_' . $privacyLocale] = $this->stripControllerSectionFromPrivacyText(
                $this->buildPrivacyTemplateText($layer1, $privacyLocale),
                $privacyLocale
            );
            $model['layer2']['user_privacy_text_' . $privacyLocale] = $this->stripObsoletePrivacyReferences(
                $model['layer2']['user_privacy_text_' . $privacyLocale],
                $privacyLocale
            );
            $model['layer2']['user_privacy_text'] = $model['layer2']['user_privacy_text_' . $privacyLocale];
            $message = __('messages.legal.privacy.layer2.regenerated', [], $editorLocale);
        } else {
            $model['layer2']['user_privacy_text_' . $privacyLocale] = $this->stripControllerSectionFromPrivacyText(
                trim((string) $request->input('privacy_user_text')),
                $privacyLocale
            );
            $model['layer2']['user_privacy_text_' . $privacyLocale] = $this->stripObsoletePrivacyReferences(
                $model['layer2']['user_privacy_text_' . $privacyLocale],
                $privacyLocale
            );
            $model['layer2']['user_privacy_text'] = $model['layer2']['user_privacy_text_' . $privacyLocale];
            $message = __('messages.legal.privacy.layer2.saved', [], $editorLocale);
        }

        $model['layer2']['template_version'] = '2026-03';
        $model['layer2']['template_locale'] = $privacyLocale;

        $this->savePrivacyLayerModel($activeEditorUserId, $model);

        return redirect()->route('showLegal', ['page_id' => $activeEditorUserId])
            ->with('success', $message);
    }

    public function savePrivacyLayer3(Request $request)
    {
        $activeEditorUserId = $this->activeEditorUserId($request);
        $editorLocale = $this->privacyLocaleForUserId($activeEditorUserId);
        App::setLocale($editorLocale);
        $submittedPageId = $request->filled('page_id') ? (int) $request->input('page_id') : null;
        $this->denyTenantPageContextMismatch(
            request: $request,
            activeResourceUserId: $activeEditorUserId,
            suppliedPageId: $submittedPageId,
            reasonCode: 'tenant_page_context_mismatch_save_privacy_layer3',
            message: __('messages.legal.hub_context_changed', [], $editorLocale),
        );

        $validator = Validator::make($request->all(), [
            'privacy_embed_mode' => ['required', 'in:auto,self'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $embedMode = trim((string) $request->input('privacy_embed_mode', 'auto'));

        $imprintLink = $this->firstImprintLinkForUser($activeEditorUserId);
        $storedImprintParams = $this->decodedTypeParams($imprintLink?->type_params);
        $privacyLocale = $editorLocale;
        $model = $this->privacyLayerModelForUser($activeEditorUserId, $storedImprintParams, $privacyLocale);
        $model['layer3'] = [
            'embed_mode' => $embedMode,
            'self_responsibility_acknowledged' => $embedMode === 'self',
            'self_responsibility_acknowledged_at' => $embedMode === 'self' ? now()->toIso8601String() : null,
        ];

        $this->savePrivacyLayerModel($activeEditorUserId, $model);

        return redirect()->route('showLegal', ['page_id' => $activeEditorUserId])
            ->with('success', __('messages.legal.privacy.layer3.saved', [], $editorLocale));
    }

    public function savePrivacyAllLayers(Request $request)
    {
        $activeEditorUserId = $this->activeEditorUserId($request);
        $editorLocale = $this->privacyLocaleForUserId($activeEditorUserId);
        App::setLocale($editorLocale);
        $submittedPageId = $request->filled('page_id') ? (int) $request->input('page_id') : null;
        $this->denyTenantPageContextMismatch(
            request: $request,
            activeResourceUserId: $activeEditorUserId,
            suppliedPageId: $submittedPageId,
            reasonCode: 'tenant_page_context_mismatch_save_privacy_all',
            message: __('messages.legal.hub_context_changed', [], $editorLocale),
        );

        $action = trim((string) $request->input('layer2_action', 'save'));

        $validator = Validator::make($request->all(), [
            'privacy_user_text' => ['nullable', 'string', 'max:120000'],
            'layer2_action' => ['nullable', 'in:save,regenerate'],
            'privacy_embed_mode' => ['required', 'in:auto,self'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $imprintLink = $this->firstImprintLinkForUser($activeEditorUserId);
        $storedImprintParams = $this->decodedTypeParams($imprintLink?->type_params);
        $privacyLocale = $editorLocale;
        $model = $this->privacyLayerModelForUser($activeEditorUserId, $storedImprintParams, $privacyLocale);
        $imprintLayer1 = $this->privacyLayer1ImprintDefaults($storedImprintParams, $privacyLocale);
        $existingOverrides = $this->normalizePrivacyLayer1(
            (array) ($model['layer1_overrides'] ?? []),
            array_fill_keys($this->privacyLayer1SyncableKeys(), '')
        );

        $submittedSync = [];
        foreach ($this->privacyLayer1SyncableKeys() as $key) {
            $submittedSync[$key] = $request->boolean('privacy_sync_' . $key);
        }

        $submittedOverrides = [
            'controller_name' => trim((string) $request->input('privacy_controller_name')),
            'street' => trim((string) $request->input('privacy_street')),
            'postal_code' => trim((string) $request->input('privacy_postal_code')),
            'city' => trim((string) $request->input('privacy_city')),
            'country' => trim((string) $request->input('privacy_country')),
            'email' => trim((string) $request->input('privacy_email')),
            'phone' => trim((string) $request->input('privacy_phone')),
            'dpo_name' => trim((string) $request->input('privacy_dpo_name')),
            'dpo_contact' => trim((string) $request->input('privacy_dpo_contact')),
        ];

        foreach ($this->privacyLayer1SyncableKeys() as $key) {
            if ($submittedSync[$key]) {
                $submittedOverrides[$key] = trim((string) ($existingOverrides[$key] ?? ''));
            }
        }

        $effectiveLayer1 = $this->applyPrivacyLayer1Sync($imprintLayer1, $submittedOverrides, $submittedSync);
        $effectiveLayer1['dpo_name'] = trim((string) $submittedOverrides['dpo_name']);
        $effectiveLayer1['dpo_contact'] = trim((string) $submittedOverrides['dpo_contact']);

        $effectiveValidator = Validator::make($effectiveLayer1, [
            'controller_name' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:64'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'dpo_name' => ['nullable', 'string', 'max:255'],
            'dpo_contact' => ['nullable', 'string', 'max:500'],
        ]);
        if ($effectiveValidator->fails()) {
            return back()->withErrors($effectiveValidator)->withInput();
        }

        $model['layer1'] = $effectiveLayer1;
        $model['layer1_sync'] = $submittedSync;
        $model['layer1_overrides'] = $this->normalizePrivacyLayer1($submittedOverrides, array_fill_keys($this->privacyLayer1SyncableKeys(), ''));
        $model['layer1_imprint_defaults'] = $imprintLayer1;

        if ($action === 'regenerate') {
            $layer2Text = $this->stripControllerSectionFromPrivacyText(
                $this->buildPrivacyTemplateText($effectiveLayer1, $privacyLocale),
                $privacyLocale
            );
            $layer2Text = $this->stripObsoletePrivacyReferences($layer2Text, $privacyLocale);
            $message = __('messages.legal.privacy.layer2.regenerated', [], $editorLocale);
        } else {
            $layer2Text = $this->stripControllerSectionFromPrivacyText(
                trim((string) $request->input('privacy_user_text')),
                $privacyLocale
            );
            $layer2Text = $this->stripObsoletePrivacyReferences($layer2Text, $privacyLocale);
            $message = __('messages.legal.privacy.saved_all', [], $editorLocale);
        }

        $model['layer2']['user_privacy_text_' . $privacyLocale] = $layer2Text;
        $model['layer2']['user_privacy_text'] = $layer2Text;
        $model['layer2']['template_version'] = '2026-03';
        $model['layer2']['template_locale'] = $privacyLocale;

        $embedMode = trim((string) $request->input('privacy_embed_mode', 'auto'));
        $model['layer3'] = [
            'embed_mode' => $embedMode,
            'self_responsibility_acknowledged' => $embedMode === 'self',
            'self_responsibility_acknowledged_at' => $embedMode === 'self' ? now()->toIso8601String() : null,
        ];

        $this->savePrivacyLayerModel($activeEditorUserId, $model);

        return redirect()->route('showLegal', ['page_id' => $activeEditorUserId])
            ->with('success', $message);
    }

    //Save add link
    public function saveLink(Request $request)
    {
        $activeEditorUserId = $this->activeEditorUserId($request);
        $submittedPageId = $request->filled('page_id') ? (int) $request->input('page_id') : null;
        $this->denyTenantPageContextMismatch(
            request: $request,
            activeResourceUserId: $activeEditorUserId,
            suppliedPageId: $submittedPageId,
            reasonCode: 'tenant_page_context_mismatch_save_link',
            message: 'Hub context changed. Re-open Add Link for the active hub before saving.',
        );

        // Step 1: Validate Request
        // $request->validate([
        //     'link' => 'sometimes|url',
        // ]);
    
        // Step 2: Determine Link Type and Title
        $selectedTypeName = trim((string) $request->input('typename', ''));
        if ($selectedTypeName === '') {
            return back()->withErrors([
                'typename' => 'Please select a block before saving.',
            ])->withInput();
        }

        $existingLinkId = (int) $request->input('linkid', 0);
        $existingLink = $existingLinkId > 0
            ? Link::where('id', $existingLinkId)->where('user_id', $activeEditorUserId)->first()
            : null;
        if ($existingLinkId > 0 && !$existingLink) {
            abort(404);
        }
        if ($existingLink && (string) ($existingLink->type ?? '') === 'imprint') {
            return redirect()->route('showLegal', ['page_id' => $activeEditorUserId]);
        }
        if ($existingLink) {
            $currentType = trim((string) ($existingLink->type ?? ''));
            if ($currentType === '') {
                $currentType = 'predefined';
            }
            if ($currentType !== '' && $selectedTypeName !== $currentType) {
                return back()->withErrors([
                    'typename' => 'Der Blocktyp kann bei bestehenden Blöcken nicht geändert werden.',
                ])->withInput();
            }
        }

        if ($selectedTypeName === 'imprint') {
            return redirect()->route('showLegal', ['page_id' => $activeEditorUserId]);
        }

        $request->merge(['typename' => $selectedTypeName]);
        $linkType = LinkType::findByTypename($selectedTypeName);
        $LinkTitle = trim(strip_tags((string) $request->title));
        $LinkURL = trim((string) $request->link);

        if ($selectedTypeName == 'predefined' || $selectedTypeName == 'link') {
            if ($LinkURL !== '' && !preg_match('/^(https?:|mailto:|tel:)/i', $LinkURL)) {
                $LinkURL = 'https://' . ltrim($LinkURL);
            }

            $validator = Validator::make(
                ['link' => $LinkURL, 'title' => $LinkTitle],
                ['link' => 'required|exturl|max:2048', 'title' => 'nullable|string|max:255']
            );

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }
        }

        // Step 3: Load Link Type Logic
        if($selectedTypeName == 'predefined' || $selectedTypeName == 'link') {
            // Determine button id based on whether a custom or predefined button is used
            $button_id = ($selectedTypeName == 'link') ? ($request->boolean('GetSiteIcon') ? 2 : 1) : null;
            $button = ($selectedTypeName != 'link') ? Button::where('name', $request->button)->first() : null;
            if ($selectedTypeName == 'predefined' && !$button) {
                return back()->withErrors([
                    'button' => 'Selected button is no longer available.',
                ])->withInput();
            }

            $showWebsiteIcon = ((int) $button_id === 2);
            $customIconValue = null;
            if ($selectedTypeName == 'link') {
                $showIcon = $request->boolean('show_icon');
                $normalizedIcon = $this->normalizeCustomLinkIcon((string) $request->input('custom_icon', ''));

                if ($showWebsiteIcon) {
                    // Website icon mode is exclusive and always uses favicon/default website icon.
                    $customIconValue = '';
                } elseif (!$showIcon) {
                    $customIconValue = 'ls-hidden-icon';
                } elseif ($normalizedIcon === null || $normalizedIcon === '') {
                    $customIconValue = 'fa-external-link';
                } else {
                    $customIconValue = $normalizedIcon;
                }
            }

            $linkData = [
                'link' => $LinkURL,
                'title' => $LinkTitle ?? $button?->alt,
                'user_id' => $activeEditorUserId,
                'button_id' => $button?->id ?? $button_id,
                'type' => $selectedTypeName // Save the link type
            ];

            if ($selectedTypeName == 'link') {
                $linkData['custom_icon'] = $customIconValue;
            }
        } else {
            if (!$linkType) {
                return back()->withErrors([
                    'typename' => 'Selected block is no longer available.',
                ])->withInput();
            }

            $linkTypePath = base_path("blocks/{$linkType->typename}/handler.php");
            if (file_exists($linkTypePath)) {
                include $linkTypePath;
                $result = $this->executeBlockHandler($request, $linkType);
                
                // Extract rules and linkData from the result
                $rules = $result['rules'];
                $linkData = $result['linkData'];
            
                // Validate the request
                $validator = Validator::make($request->all(), $rules);

                // Check if validation fails
                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                }

                $linkData['button_id'] = $linkData['button_id'] ?? 1; // Set 'button_id' unless overwritten by handleLinkType
                $linkData['type'] = $linkType->typename; // Ensure 'type' is included in $linkData
            } else {
                abort(404, "Link type logic not found.");
            }
        }   

        // Step 4: Handle Custom Parameters
        // (Same as before)

        // Step 4b: Adult content flag
        $linkData['is_adult'] = $request->boolean('is_adult');

        // Step 5: User and Button Information
        $userId = $activeEditorUserId;
        $tenantOwnerId = $this->tenantOwnerIdForEditorUser($userId);
        $button = Button::where('name', $request->button)->first();
        if ($button && empty($LinkTitle)) $LinkTitle = $button->alt;
        $linkData['user_id'] = $linkData['user_id'] ?? $userId;
        $resolvedButtonId = $this->resolvePersistableButtonId($linkData['button_id'] ?? null);
        if ($resolvedButtonId === null) {
            return back()->withErrors([
                'button' => 'Selected button is no longer available.',
            ])->withInput();
        }
        $linkData['button_id'] = $resolvedButtonId;
        if ($tenantOwnerId !== null && Schema::hasColumn('links', 'tenant_owner_user_id')) {
            $linkData['tenant_owner_user_id'] = $tenantOwnerId;
        }

        // Step 6: Prepare Link Data
        // (Handled by the included file)

        // Step 7: Save or Update Link
        $OrigLink = $existingLink;
        if ($OrigLink && (string) ($OrigLink->type ?? '') === 'imprint') {
            return redirect()->route('showLegal', ['page_id' => $activeEditorUserId]);
        }
        $linkColumns = Schema::getColumnListing('links'); // Get all column names of links table
        $filteredLinkData = array_intersect_key($linkData, array_flip($linkColumns)); // Filter $linkData to only include keys that are columns in the links table

        // Combine remaining variables into one array and convert to JSON for the type_params column
        $customParams = array_diff_key($linkData, $filteredLinkData);

            // Check if $linkType->custom_html is defined and not null
            if (isset($linkType->custom_html)) {
                // Add $linkType->custom_html to the $customParams array
                $customParams['custom_html'] = $linkType->custom_html;
            }

            // Check if $linkType->ignore_container is defined and not null
            if (isset($linkType->ignore_container)) {
                // Add $linkType->ignore_container to the $customParams array
                $customParams['ignore_container'] = $linkType->ignore_container;
            }

            // Check if $linkType->include_libraries is defined and not null
            if (isset($linkType->include_libraries)) {
                // Add $linkType->include_libraries to the $customParams array
                $customParams['include_libraries'] = $linkType->include_libraries;
            }
        
        $filteredLinkData['type_params'] = json_encode($customParams);

        if ($OrigLink) {
            $currentValues = $OrigLink->getAttributes();
            $nonNullFilteredLinkData = array_filter($filteredLinkData, function($value) {return !is_null($value);});
            $updatedValues = array_merge($currentValues, $nonNullFilteredLinkData);
            $OrigLink->update($updatedValues);
            $message = "Link updated";
        } else {
            $link = new Link($filteredLinkData);
            $link->user_id = $userId;
            $link->save();
            $message = "Link added";
        }

        // Step 8: Redirect
        $redirectUrl = $request->input('param') == 'add_more'
            ? url('/studio/add-link?page_id=' . $userId)
            : url('/studio/links');
        return Redirect($redirectUrl)->with('success', $message);
    }
    
    public function sortLinks(Request $request)
    {
        $userId = $this->activeEditorUserId($request);
        $linkOrders  = $request->input("linkOrders", []);
        $currentPage = $request->input("currentPage", 1);
        $perPage     = $request->input("perPage", 0);

        if ($perPage == 0) {
            $currentPage = 1;
        }

        $linkOrders = array_unique(array_filter($linkOrders));
        if (!$linkOrders || $currentPage < 1) {
            return response()->json([
                'status' => 'ERROR',
            ]);
        }

        $newOrder = $perPage * ($currentPage - 1);
        $linkNewOrders = [];
        foreach ($linkOrders as $linkId) {
            if ($linkId < 0) {
                continue;
            }

            $linkNewOrders[$linkId] = $newOrder;
            Link::where("id", $linkId)
                ->where('user_id', $userId)
                ->update([
                    'order' => $newOrder
                ]);
            $newOrder++;
        }

        return response()->json([
            'status' => 'OK',
            'linkOrders' => $linkNewOrders,
        ]);
    }


    //Redirect to link and forward event to analytics pipeline
    public function clickNumber(request $request)
    {
        $linkId = $request->id;

        if (substr($linkId, -1) == '+') {
            $linkWithoutPlus = str_replace('+', '', $linkId);
            return redirect(url('info/'.$linkWithoutPlus));
        }
    
        $linkModel = Link::find($linkId);

        if (empty($linkModel)) {
            return abort(404);
        }

        $link = $linkModel->link;

        if (empty($linkId)) {
            return abort(404);
        }

        $owner = null;
        if (!empty($linkModel->user_id)) {
            $owner = $this->publicPageUserQuery()->find((int) $linkModel->user_id);
        }

        if (!$owner || !$this->canRenderPublishedHub($owner, Auth::user())) {
            return abort(404);
        }

        $metadata = [
            'page_id' => $linkModel->user_id,
            'page_slug' => $owner->littlelink_name ?? null,
        ];
        app(AnalyticsDispatcher::class)->recordLinkClick($request, $owner, (int) $linkId, $metadata);
        if (empty($link) || !is_string($link)) {
            return abort(404);
        }

        $response = redirect()->away($link);
        $response->header('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    //Download Vcard
    public function vcard(request $request)
    {
        $linkId = $request->id;

        // Find the link with the specified ID
        $link = Link::findOrFail($linkId);
        if ((string) ($link->type ?? '') !== 'vcard') {
            return abort(404);
        }

        if (app(AccountLifecycleService::class)->publicPageUnavailableReason((int) $link->user_id) !== null) {
            return abort(404);
        }

        $owner = $this->publicPageUserQuery()->find((int) $link->user_id);
        if (!$owner || !$this->canRenderPublishedHub($owner, Auth::user())) {
            return abort(404);
        }

        $json = $link->link;

        // Decode the JSON to a PHP array
        $data = json_decode($json, true);
        
        // Create a new vCard object
        $vcard = new VCard();
        
        // Set the vCard properties from the $data array
        $vcard->addName($data['last_name'], $data['first_name'], $data['middle_name'], $data['prefix'], $data['suffix']);
        $vcard->addCompany($data['organization']);
        $vcard->addJobtitle($data['vtitle']);
        $vcard->addRole($data['role']);
        $vcard->addEmail($data['email']);
        $vcard->addEmail($data['work_email'], 'WORK');
        $vcard->addURL($data['work_url'], 'WORK');
        $vcard->addPhoneNumber($data['home_phone'], 'HOME');
        $vcard->addPhoneNumber($data['work_phone'], 'WORK');
        $vcard->addPhoneNumber($data['cell_phone'], 'CELL');
        $vcard->addAddress($data['home_address_street'], '', $data['home_address_city'], $data['home_address_state'], $data['home_address_zip'], $data['home_address_country'], 'HOME');
        $vcard->addAddress($data['work_address_street'], '', $data['work_address_city'], $data['work_address_state'], $data['work_address_zip'], $data['work_address_country'], 'WORK');
        

        // $vcard->addPhoto(base_path('img/1.png'));
        
        // Generate the vCard file contents
        $file_contents = $vcard->getOutput();
        
        // Set the file headers for download
        $headers = [
            'Content-Type' => 'text/x-vcard',
            'Content-Disposition' => 'attachment; filename="contact.vcf"'
        ];
        
        // Return the file download response
        return response()->make($file_contents, 200, $headers);

    }

    //Show link, click number, up link in links page
    public function showLinks()
    {
        $userId = $this->activeEditorUserId();
        $data['pagePage'] = 50;
        $data['activePageSlug'] = (string) User::whereKey($userId)->value('littlelink_name');
        
        $data['links'] = Link::select()
            ->where('user_id', $userId)
            ->where(static function ($query) {
                $query->whereNull('type')->orWhere('type', '!=', 'imprint');
            })
            ->orderBy('up_link', 'asc')
            ->orderBy('order', 'asc')
            ->paginate($data['pagePage']);

        $data['links']->getCollection()->transform(function (Link $link) {
            $typeParams = $this->decodedTypeParams($link->type_params);
            $publicTitle = trim(strip_tags((string) ($link->title ?? '')));
            $internalTitle = trim(strip_tags((string) ($typeParams['internal_title'] ?? '')));
            $hasFallbackTitle = filter_var(($typeParams['title_is_fallback'] ?? false), FILTER_VALIDATE_BOOLEAN);

            $fallbackTitle = $publicTitle;
            if ($fallbackTitle === '' && (string) ($link->type ?? '') === 'hub_contact_form') {
                $fallbackTitle = __('Contact form');
            }
            if ($fallbackTitle === '' && (string) ($link->type ?? '') === 'smart_embed') {
                $fallbackTitle = str_starts_with(strtolower((string) app()->getLocale()), 'de')
                    ? 'Externer Inhalt'
                    : 'Embedded content';
            }

            $link->studio_title = $internalTitle !== '' ? $internalTitle : $fallbackTitle;
            $link->studio_uses_fallback_title = ($internalTitle === '' && $hasFallbackTitle);

            return $link;
        });

        return view('studio/links', $data);
    }

    //Delete link
    public function deleteLink(request $request)
    {
        $linkId = $request->id;
        $userId = $this->activeEditorUserId($request);

        $link = Link::where('id', $linkId)->where('user_id', $userId)->firstOrFail();
        $link->delete();

        foreach (glob(base_path("assets/favicon/icons/{$linkId}.*")) ?: [] as $iconPath) {
            try {
                File::delete($iconPath);
            } catch (exception $e) {
            }
        }

        return redirect('/studio/links');
    }

    //Delete icon
    public function clearIcon(request $request)
    {
        $linkId = $request->id;
        $userId = $this->activeEditorUserId($request);

        Link::where('id', $linkId)->where('user_id', $userId)->firstOrFail();

        foreach (glob(base_path("assets/favicon/icons/{$linkId}.*")) ?: [] as $iconPath) {
            try {
                File::delete($iconPath);
            } catch (exception $e) {
            }
        }

        return redirect('/studio/links');
    }

    //Raise link on the littlelink page
    public function upLink(request $request)
    {
        $linkId = $request->id;
        $upLink = $request->up;

        if ($upLink == 'yes') {
            $up = 'no';
        } elseif ($upLink == 'no') {
            $up = 'yes';
        } else {
            abort(400);
        }

        $updated = Link::where('id', $linkId)
            ->where('user_id', $this->activeEditorUserId($request))
            ->update(['up_link' => $up]);
        if ($updated === 0) {
            abort(403);
        }

        return back();
    }

    //Show link to edit
    public function showLink(request $request)
    {
        $linkId = $request->id;
        $userId = $this->activeEditorUserId($request);

        $linkModel = Link::where('id', $linkId)->where('user_id', $userId)->firstOrFail();
        $link = $linkModel->link;
        $title = $linkModel->title;
        $order = $linkModel->order;
        $custom_css = $linkModel->custom_css;
        $buttonId = $linkModel->button_id;
        $buttonName = Button::where('id', $buttonId)->value('name');
        $previewSessionKey = 'button_editor_preview_link_id_'.$userId;

        // Remember the currently edited custom/custom_website link for button editor preview.
        if (in_array((int) $buttonId, [1, 2], true)) {
            $request->session()->put($previewSessionKey, (int) $linkId);
        }

        $buttons = Button::select('id', 'name')->orderBy('name', 'asc')->get();

        return view('studio/edit-link', ['custom_css' => $custom_css, 'buttonId' => $buttonId, 'buttons' => $buttons, 'link' => $link, 'title' => $title, 'order' => $order, 'id' => $linkId, 'buttonName' => $buttonName]);
    }

    //Show global custom CSS editor for custom-style links
    public function showCSS(request $request)
    {
        $this->ensureOwnerFeatureAccess(
            $request,
            'design.link_styling',
            'Button styling is not enabled for your tier.'
        );

        $userId = $this->activeEditorUserId($request);
        $previewSessionKey = 'button_editor_preview_link_id_'.$userId;
        $linkId = $request->route('id');
        $rememberedPreviewLinkId = (int) $request->session()->get($previewSessionKey, 0);

        $previewLink = null;
        if (!empty($linkId)) {
            $previewLink = Link::where('id', $linkId)
                ->where('user_id', $userId)
                ->whereIn('button_id', [1, 2])
                ->first();
        }

        if (!$previewLink && $rememberedPreviewLinkId > 0) {
            $previewLink = Link::where('id', $rememberedPreviewLinkId)
                ->where('user_id', $userId)
                ->whereIn('button_id', [1, 2])
                ->first();
        }

        if (!$previewLink) {
            $previewLink = Link::where('user_id', $userId)
                ->whereIn('button_id', [1, 2])
                ->orderBy('updated_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();
        }

        if ($previewLink) {
            $request->session()->put($previewSessionKey, (int) $previewLink->id);
        }

        $normalizeCss = static function ($value): string {
            if (!is_string($value)) {
                return '';
            }

            $value = trim($value);
            if ($value === '' || strtolower($value) === 'null') {
                return '';
            }

            return $value;
        };

        $globalCustomCss = $normalizeCss(UserData::getData($userId, 'global_custom_button_css'));
        $previewLinkCustomCss = $normalizeCss($previewLink->custom_css ?? null);
        $effectiveCustomCss = $globalCustomCss;

        // Backward compatibility: if global style is empty, use the style currently visible on the active preview link.
        // This keeps the editor preview in sync with what users actually see on their page.
        if ($effectiveCustomCss === '') {
            $legacyCustomCss = $previewLinkCustomCss;

            if ($legacyCustomCss === '') {
                $legacyCustomCss = $normalizeCss(
                    Link::where('user_id', $userId)
                        ->whereIn('button_id', [1, 2])
                        ->whereNotNull('custom_css')
                        ->where('custom_css', '<>', '')
                        ->orderBy('updated_at', 'desc')
                        ->orderBy('id', 'desc')
                        ->value('custom_css')
                );
            }

            if ($legacyCustomCss !== '') {
                $effectiveCustomCss = $legacyCustomCss;
                UserData::saveData($userId, 'global_custom_button_css', $effectiveCustomCss);
            }
        }

        $previewButtonId = (int) ($previewLink->button_id ?? 1);
        if (!in_array($previewButtonId, [1, 2], true)) {
            $previewButtonId = 1;
        }

        $previewIcon = (string) ($previewLink->custom_icon ?? 'fa-link');
        if ($previewIcon === '') {
            $previewIcon = 'fa-link';
        }

        $previewTitle = trim((string) ($previewLink->title ?? ''));
        if ($previewTitle === '') {
            $previewTitle = __('messages.Custom Button');
        }

        $activeTheme = (string) (User::query()->where('id', $userId)->value('theme') ?? 'default');
        $activeTemplate = app(TemplateCatalogService::class)->templateByTheme($activeTheme);
        $templateAllowsCustomButtons = app(TemplateCatalogService::class)->capabilityForTheme(
            $activeTheme,
            'custom_buttons',
            $activeTheme === 'default'
        );

        return view('studio/button-editor', [
            'custom_icon' => $previewIcon,
            'custom_css' => $effectiveCustomCss,
            'buttonId' => $previewButtonId,
            'link' => (string) ($previewLink->link ?? ''),
            'title' => $previewTitle,
            'order' => (int) ($previewLink->order ?? 0),
            'id' => $previewLink->id ?? null,
            'activeTemplateLabel' => (string) ($activeTemplate['label'] ?? $activeTheme),
            'templateAllowsCustomButtons' => $templateAllowsCustomButtons,
        ]);
    }

    //Save edit link
    public function editLink(request $request)
    {
        $request->validate([
            'link' => 'required|exturl',
            'title' => 'required',
            'button' => 'required',
        ]);

        if (stringStartsWith($request->link, 'http://') == 'true' or stringStartsWith($request->link, 'https://') == 'true' or stringStartsWith($request->link, 'mailto:') == 'true')
            $link1 = $request->link;
        else
            $link1 = 'https://' . $request->link;
        if (stringEndsWith($request->link, '/') == 'true')
            $link = rtrim($link1, "/ ");
        else
        $link = $link1;
        $title = trim(strip_tags((string) $request->title));
        $order = $request->order;
        $button = $request->button;
        $linkId = $request->id;
        $userId = $this->activeEditorUserId($request);

        $buttonId = Button::select('id')->where('name', $button)->value('id');

        $updates = ['link' => $link, 'title' => $title, 'order' => $order, 'button_id' => $buttonId];
        $tenantOwnerId = $this->tenantOwnerIdForEditorUser($userId);
        if ($tenantOwnerId !== null && Schema::hasColumn('links', 'tenant_owner_user_id')) {
            $updates['tenant_owner_user_id'] = $tenantOwnerId;
        }

        Link::where('id', $linkId)
            ->where('user_id', $userId)
            ->update($updates);

        return redirect('/studio/links');
    }

    //Save global custom CSS used for custom-style links
    public function editCSS(request $request)
    {
        $this->ensureOwnerFeatureAccess(
            $request,
            'design.link_styling',
            'Button styling is not enabled for your tier.'
        );

        $request->validate([
            'custom_css' => 'nullable|string|max:2000',
        ]);

        $customCss = trim((string) $request->input('custom_css', ''));
        UserData::saveData($this->activeEditorUserId($request), 'global_custom_button_css', $customCss);

        $previewLinkId = (int) $request->route('id');
        if ($previewLinkId > 0) {
            $request->session()->put('button_editor_preview_link_id_'.$this->activeEditorUserId($request), $previewLinkId);
        }

        return redirect()->route('showCSS');
    }

    //Show littlelinke page for edit
    public function showPage(request $request)
    {
        $userId = $this->activeEditorUserId($request);
        $owner = $request->user();

        $query = User::where('id', $userId)
            ->select('id', 'littlelink_name', 'littlelink_description', 'image', 'name', 'theme');
        if (Schema::hasColumn('users', 'locale')) {
            $query->addSelect('locale');
        }
        if (Schema::hasColumn('users', 'is_published')) {
            $query->addSelect('is_published');
        }
        if (Schema::hasColumn('users', 'published_at')) {
            $query->addSelect('published_at');
        }

        $data['pages'] = $query->get();
        $contextUser = $data['pages']->first();
        $data['supportedLocales'] = config('app.supported_locales', []);
        $data['pageLocale'] = is_object($contextUser) ? ($contextUser->locale ?? null) : null;
        $data['isPageHubContext'] = $owner && $contextUser
            ? (int) ($contextUser->id ?? 0) !== (int) $owner->id
            : false;
        $data['pageContextName'] = trim((string) ($contextUser->name ?? $contextUser->littlelink_name ?? ''));
        $data['agencyLocale'] = is_string($owner?->locale ?? null) ? (string) $owner->locale : null;

        return view('/studio/page', $data);
    }

    public function setPublication(Request $request)
    {
        $actor = $request->user();
        if (!$actor) {
            abort(403);
        }

        $validated = $request->validate([
            'publish' => ['required', 'boolean'],
        ]);

        $targetUserId = $this->activeEditorUserId($request);
        $targetUser = User::query()->findOrFail($targetUserId);

        $service = app(HubPublicationService::class);
        if (!$service->publicationColumnsReady()) {
            return back()->withErrors([
                'hub' => __('messages.hub.publish.error_unavailable'),
            ]);
        }

        if (!$service->canManagePublication($actor, $targetUser)) {
            abort(403);
        }

        $publish = (bool) $validated['publish'];
        $service->setPublicationState(
            $actor,
            $targetUser,
            $publish,
            $request,
            'studio.page.publication'
        );

        $message = $publish
            ? __('messages.hub.publish.success_published')
            : __('messages.hub.publish.success_unpublished');

        return back()->with('success', $message);
    }

    public function validateStudioHandle(Request $request)
    {
        $userId = $this->activeEditorUserId($request);

        $validator = Validator::make($request->all(), [
            'littlelink_name' => [
                'required',
                'string',
                'max:25',
                'isunique:users,id,'.$userId,
                Rule::notIn(reservedSlugs()),
            ],
        ]);

        return response()->json(['valid' => !$validator->fails()]);
    }

    //Save littlelink page (name, description, logo)
    public function editPage(Request $request)
    {
        $userId = $this->activeEditorUserId($request);
        $currentEditor = User::query()->findOrFail($userId);
        $returnTo = (string) $request->input('return_to', '/studio/page');
        $allowedReturnPaths = [
            '/studio/page',
            '/studio/page-settings',
            '/studio/no_page_name',
            '/studio/header',
            '/studio/behavior',
        ];
        if (!in_array($returnTo, $allowedReturnPaths, true)) {
            $returnTo = '/studio/page';
        }
        $littlelink_name = $currentEditor->littlelink_name;
        $avatarLimit = $this->imageUploadLimit('avatar', 2048, 1200, 1200);
    
        $validator = Validator::make($request->all(), [
            'littlelink_name' => [
                'sometimes',
                'max:25',
                'string',
                'isunique:users,id,'.$userId,
                Rule::notIn(reservedSlugs()),
            ],
            'name' => 'sometimes|max:30|string',
            'image' => 'sometimes|image|mimes:jpeg,jpg,png,webp|max:'.$avatarLimit['max_kb'].'|dimensions:max_width='.$avatarLimit['max_width'].',max_height='.$avatarLimit['max_height'],
            'show_profile_image' => 'nullable|boolean',
            'pageDescription' => 'nullable|string|max:75',
            'profile_header_layout' => 'nullable|in:standard,business,business_header_focus_description',
            'global_text_color_mode' => 'nullable|in:black,white,custom',
            'global_text_color_custom' => ['nullable', 'regex:/^#?(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ], [
            'littlelink_name.unique' => __('messages.That handle has already been taken'),
            'image.image' => __('messages.The selected file must be an image'),
            'image.mimes' => __('messages.The image must be') . ' JPEG, JPG, PNG, webP.',
            'image.max' => __('messages.upload.validation.max', [
                'label' => __('messages.Profile Picture'),
                'size' => $avatarLimit['max_mb'],
            ]),
            'image.dimensions' => __('messages.upload.validation.dimensions', [
                'label' => __('messages.Profile Picture'),
                'width' => $avatarLimit['max_width'],
                'height' => $avatarLimit['max_height'],
            ]),
        ]);
    
        if ($validator->fails()) {
            return redirect($returnTo)->withErrors($validator)->withInput();
        }
    
        $profilePhoto = $request->file('image');
        $pageName = $request->littlelink_name;
        $pageDescription = trim(strip_tags((string) $request->pageDescription));
        $name = trim(strip_tags((string) $request->name));
        $checkmark = $request->checkmark;
        $sharebtn = $request->sharebtn;
        $tablinks = $request->tablinks;
        $hideCredit = $request->hide_credit;
        $showProfileImage = $request->boolean('show_profile_image', true);
        $mediaStorage = app(MediaStorageService::class);
        $subscriptionManager = class_exists(\Modules\Tiers\Services\SubscriptionManager::class) ? app(\Modules\Tiers\Services\SubscriptionManager::class) : null;
        $userTier = $subscriptionManager ? $subscriptionManager->getUserTier(Auth::user()) : null;
        $tierResolver = class_exists(TierResolver::class) ? app(TierResolver::class) : null;
        $canRemoveBranding = ($tierResolver && $userTier && $tierResolver->featureEnabled($userTier, 'branding.remove_branding'))
            || in_array(Auth::user()->role, ['vip', 'admin'], true);
        $canUseCheckmark = ($tierResolver && $userTier && $tierResolver->featureEnabled($userTier, 'profile.checkmark'))
            || in_array(Auth::user()->role, ['vip', 'admin'], true);
        $templateCatalog = app(TemplateCatalogService::class);
        $activeTheme = is_string($currentEditor->theme) && trim($currentEditor->theme) !== ''
            ? trim($currentEditor->theme)
            : 'default';
        $canSwitchProfileLayout = $templateCatalog->capabilityForTheme(
            $activeTheme,
            'profile_layout_switcher',
            true
        );
        $hasTextAccentInput = $request->has('global_text_color_mode')
            || $request->has('global_text_color_custom')
            || $request->has('global_text_color');

        if (
            env('HOME_URL') !== ''
            && $pageName != $littlelink_name
            && $littlelink_name == env('HOME_URL')
            && (int) $userId === (int) Auth::id()
        ) {
            EnvEditor::editKey('HOME_URL', $pageName);
        }
    
        User::where('id', $userId)->update([
            'littlelink_name' => $pageName,
            'littlelink_description' => $pageDescription,
            'name' => $name
        ]);
    
        if ($request->hasFile('image')) {
            $mediaStorage->deleteAvatarForUser($userId);
            $avatarKey = $mediaStorage->storeUploadedFile($userId, $profilePhoto, 'avatars');
            UserData::saveData($userId, $mediaStorage->avatarDataKey(), $avatarKey);
        }
    
        if ($checkmark == "on" && $canUseCheckmark) {
            UserData::saveData($userId, 'checkmark', true);
        } else {
            UserData::saveData($userId, 'checkmark', false);
        }
    
        if ($sharebtn == "on") {
            UserData::saveData($userId, 'disable-sharebtn', false);
        } else {
            UserData::saveData($userId, 'disable-sharebtn', true);
        }

        if ($tablinks == "on") {
            UserData::saveData($userId, 'links-new-tab', true);
        } else {
            UserData::saveData($userId, 'links-new-tab', false);
        }

        $hideCreditEnabled = $canRemoveBranding && $hideCredit == "on";
        UserData::saveData($userId, 'hide_credit', $hideCreditEnabled);
        UserData::saveData($userId, 'show_profile_image', $showProfileImage);
        if ($request->has('profile_header_layout') && $canSwitchProfileLayout) {
            $requestedProfileHeaderLayout = strtolower((string) $request->input('profile_header_layout', 'standard'));
            $profileHeaderLayout = in_array($requestedProfileHeaderLayout, ['standard', 'business', 'business_header_focus_description'], true)
                ? $requestedProfileHeaderLayout
                : 'standard';
            UserData::saveData($userId, 'profile_header_layout', $profileHeaderLayout);
        }

        if ($hasTextAccentInput) {
            $canCustomizeTextAccentByTier = ($tierResolver && $userTier && (
                $tierResolver->featureEnabled($userTier, 'design.custom_colors')
                || $tierResolver->featureEnabled($userTier, 'design.link_styling')
            )) || in_array(Auth::user()->role, ['vip', 'admin'], true);
            $canCustomizeTextAccent = $templateCatalog->capabilityForTheme(
                $activeTheme,
                'text_accent',
                $activeTheme === 'default'
            );
            $legacyGlobalTextColor = strtolower((string) $request->input('global_text_color', ''));
            $requestedTextMode = strtolower((string) $request->input('global_text_color_mode', $legacyGlobalTextColor));
            $globalTextMode = in_array($requestedTextMode, ['black', 'white', 'custom'], true) ? $requestedTextMode : 'white';
            $savedTextColorData = resolveUserTextColorSettings($userId);
            $globalTextCustomColor = normalizeHexColor($request->input('global_text_color_custom'), $savedTextColorData['custom_color'] ?? '#FFFFFF');

            if ($canCustomizeTextAccent && $canCustomizeTextAccentByTier) {
                UserData::saveData($userId, 'text_color_mode', $globalTextMode);
                UserData::saveData($userId, 'text_color_custom', $globalTextCustomColor);
            }
        }

        $actorId = (int) Auth::id();
        $targetUser = User::query()->select('id', 'role', 'littlelink_name')->find($userId);
        $isHubContext = $targetUser
            && (
                (int) $targetUser->id !== $actorId
                || (string) $targetUser->role === User::ROLE_AGENCY_HUB
            );
        app(ComplianceAuditService::class)->record(
            $isHubContext ? 'hub_updated' : 'page_updated',
            request: $request,
            userId: $actorId,
            actorUserId: $actorId,
            source: 'studio.page',
            metadata: [
                'target_user_id' => $userId,
                'target_slug' => $targetUser?->littlelink_name ?? $pageName,
                'is_hub_context' => (bool) $isHubContext,
                'changed_name' => $request->has('name'),
                'changed_slug' => $request->has('littlelink_name'),
                'changed_description' => $request->has('pageDescription'),
                'changed_image' => $request->hasFile('image'),
            ]
        );
    
        return Redirect($returnTo);
    }

    //Upload custom theme background image
    public function themeBackground(Request $request)
    {
        $requestedMode = strtolower(trim((string) $request->input('background_mode', 'color')));
        $hasUploadedBackground = $request->hasFile('image');
        $canUseBackgroundImages = $this->ownerCanAccessFeature('design.background_image', $request);
        $canUseCustomColors = $this->ownerCanAccessFeature('design.custom_colors', $request);
        $canUseLinkStyling = $this->ownerCanAccessFeature('design.link_styling', $request);
        if (($requestedMode === 'image' || $hasUploadedBackground) && !$canUseBackgroundImages) {
            $requiredTierLabel = $this->requiredTierLabelForFeature('design.background_image', 'Basic');

            return redirect('/dashboard/subscription')
                ->withErrors("Verfügbar ab {$requiredTierLabel}.");
        }

        if ($requestedMode === 'image') {
            $this->ensureOwnerFeatureAccess(
                $request,
                'design.background_image',
                'Background images are not enabled for your tier.'
            );
        } elseif ($requestedMode === 'color' && !$canUseCustomColors && !$canUseLinkStyling) {
            $this->ensureOwnerFeatureAccess(
                $request,
                'design.custom_colors',
                'Background color customization is not enabled for your tier.'
            );
        }
        if ($hasUploadedBackground) {
            $this->ensureOwnerFeatureAccess(
                $request,
                'design.background_image',
                'Background images are not enabled for your tier.'
            );
        }

        $userId = $this->activeEditorUserId($request);
        $templateCatalog = app(TemplateCatalogService::class);
        $mediaStorage = app(MediaStorageService::class);
        $backgroundLimit = $this->imageUploadLimit('background', 5120, 3000, 2000);
    
        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:'.$backgroundLimit['max_kb'].'|dimensions:max_width='.$backgroundLimit['max_width'].',max_height='.$backgroundLimit['max_height'],
            'background_mode' => 'required|in:color,image,template',
            'background_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'overlay_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'overlay_opacity' => 'nullable|integer|min:0|max:100',
            'gradient_enabled' => 'nullable|boolean',
            'gradient_color_one' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'gradient_color_two' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'gradient_color_three' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'gradient_stop_one' => 'nullable|integer|min:0|max:100',
            'gradient_stop_two' => 'nullable|integer|min:0|max:100',
            'gradient_stop_three' => 'nullable|integer|min:0|max:100',
            'template_background_mode' => ['nullable', 'in:default,individual'],
            'global_text_color_mode' => 'nullable|in:black,white,custom',
            'global_text_color_custom' => ['nullable', 'regex:/^#?(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ], [
            'image.image' => __('messages.The selected file must be an image'),
            'image.mimes' => __('messages.The image must be') . ' JPEG, JPG, PNG, webP.',
            'image.max' => __('messages.upload.validation.max', [
                'label' => __('messages.Background image'),
                'size' => $backgroundLimit['max_mb'],
            ]),
            'image.dimensions' => __('messages.upload.validation.dimensions', [
                'label' => __('messages.Background image'),
                'width' => $backgroundLimit['max_width'],
                'height' => $backgroundLimit['max_height'],
            ]),
        ]);
    
        $customBackground = $request->file('image');
        $baseMode = $request->input('background_mode', 'color');
        $baseColor = $request->input('background_color', '#111827');
        $overlayColor = $request->input('overlay_color', '#000000');
        $overlayOpacity = max(0, min((int)$request->input('overlay_opacity', 0), 100));
        $gradientEnabled = $request->boolean('gradient_enabled');
        $templateBackgroundMode = $request->input('template_background_mode', 'default');
    
        $buildStop = function ($color, $position, $fallback) {
            if (!$color) {
                return null;
            }
            $clampedPosition = max(0, min(100, (int)($position ?? $fallback)));
            return ['color' => $color, 'position' => $clampedPosition];
        };

        $gradientStops = array_values(array_filter([
            $buildStop($request->input('gradient_color_one'), $request->input('gradient_stop_one'), 0),
            $buildStop($request->input('gradient_color_two'), $request->input('gradient_stop_two'), 100),
            $buildStop($request->input('gradient_color_three'), $request->input('gradient_stop_three'), 50),
        ]));

        if (!empty($gradientStops)) {
            usort($gradientStops, fn ($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
        }

        if ($gradientEnabled && count($gradientStops) < 2) {
            $gradientEnabled = false;
        }

        $gradientColors = array_map(fn ($stop) => $stop['color'], $gradientStops);

        if ($baseMode === 'template') {
            $templateBackgroundMode = 'default';
            $gradientEnabled = false;
            $gradientStops = [];
            $gradientColors = [];
        }

        // When switching away from templates, fall back to the default theme so solid/image modes use the core styling.
        if ($baseMode !== 'template') {
            User::where('id', $userId)->update(['theme' => 'default']);
            $defaultTemplate = $templateCatalog->templateByTheme('default');
            if ($defaultTemplate) {
                $defaultVariant = $templateCatalog->variantForTemplateId((string) $defaultTemplate['id'], null);
                UserData::saveData($userId, 'template', (string) $defaultTemplate['id']);
                UserData::saveData($userId, 'theme_template_id', (string) $defaultTemplate['id']);
                UserData::saveData($userId, 'theme_variant_id', (string) ($defaultVariant['id'] ?? $defaultTemplate['default_variant_id'] ?? 'default'));
            }
        }

        UserData::saveData($userId, 'background_mode', $baseMode);
        UserData::saveData($userId, 'background_color', $baseColor);
        UserData::saveData($userId, 'background_overlay', [
            'color' => $overlayColor,
            'opacity' => $overlayOpacity,
        ]);
        UserData::saveData($userId, 'background_gradient', [
            'enabled' => $gradientEnabled,
            'direction' => 'to bottom',
            'stops' => $gradientStops,
            'colors' => $gradientColors,
        ]);
        UserData::saveData($userId, 'template_background_mode', $templateBackgroundMode);

        if ($baseMode === 'template') {
            $activeTheme = (string) (User::query()->where('id', $userId)->value('theme') ?? 'default');
            $activeTemplate = $templateCatalog->templateByTheme($activeTheme);
            if ($activeTemplate) {
                $rawVariantId = UserData::getData($userId, 'theme_variant_id');
                $variantId = is_string($rawVariantId) ? $rawVariantId : null;
                $resolvedVariantId = $templateCatalog->normalizeVariantId((string) $activeTemplate['id'], $variantId);
                UserData::saveData($userId, 'template', (string) $activeTemplate['id']);
                UserData::saveData($userId, 'theme_template_id', (string) $activeTemplate['id']);
                UserData::saveData($userId, 'theme_variant_id', (string) ($resolvedVariantId ?? $activeTemplate['default_variant_id'] ?? 'default'));
            }
        }
    
        if ($customBackground) {
            $mediaStorage->deleteBackgroundForUser($userId);
            $backgroundKey = $mediaStorage->storeUploadedFile($userId, $customBackground, 'backgrounds');
            UserData::saveData($userId, $mediaStorage->backgroundDataKey(), $backgroundKey);
        }
    
        if ($baseMode === 'image' && $mediaStorage->backgroundPathForUser($userId) === null) {
            UserData::saveData($userId, 'background_mode', 'color');
        }

        $hasTextAccentInput = $request->has('global_text_color_mode')
            || $request->has('global_text_color_custom')
            || $request->has('global_text_color');

        if ($hasTextAccentInput) {
            $subscriptionManager = class_exists(\Modules\Tiers\Services\SubscriptionManager::class) ? app(\Modules\Tiers\Services\SubscriptionManager::class) : null;
            $userTier = $subscriptionManager ? $subscriptionManager->getUserTier(Auth::user()) : null;
            $tierResolver = class_exists(TierResolver::class) ? app(TierResolver::class) : null;
            $canCustomizeTextAccentByTier = ($tierResolver && $userTier && (
                $tierResolver->featureEnabled($userTier, 'design.custom_colors')
                || $tierResolver->featureEnabled($userTier, 'design.link_styling')
            )) || in_array(Auth::user()->role, ['vip', 'admin'], true);

            $targetThemeForTextAccent = $baseMode !== 'template'
                ? 'default'
                : (string) (User::query()->where('id', $userId)->value('theme') ?? 'default');
            $canCustomizeTextAccent = $templateCatalog->capabilityForTheme(
                $targetThemeForTextAccent,
                'text_accent',
                $targetThemeForTextAccent === 'default'
            );
            $legacyGlobalTextColor = strtolower((string) $request->input('global_text_color', ''));
            $requestedTextMode = strtolower((string) $request->input('global_text_color_mode', $legacyGlobalTextColor));
            $globalTextMode = in_array($requestedTextMode, ['black', 'white', 'custom'], true) ? $requestedTextMode : 'white';
            $savedTextColorData = resolveUserTextColorSettings($userId);
            $globalTextCustomColor = normalizeHexColor($request->input('global_text_color_custom'), $savedTextColorData['custom_color'] ?? '#FFFFFF');

            if ($canCustomizeTextAccent && $canCustomizeTextAccentByTier) {
                UserData::saveData($userId, 'text_color_mode', $globalTextMode);
                UserData::saveData($userId, 'text_color_custom', $globalTextCustomColor);
            }
        }

        return redirect('/studio/theme');
    }

    //Delete custom background image
    public function removeBackground(Request $request)
    {
        if (!$this->ownerCanAccessFeature('design.background_image', $request)) {
            $requiredTierLabel = $this->requiredTierLabelForFeature('design.background_image', 'Basic');

            return redirect('/dashboard/subscription')
                ->withErrors("Verfügbar ab {$requiredTierLabel}.");
        }

        $userId = $this->activeEditorUserId($request);
        app(MediaStorageService::class)->deleteBackgroundForUser($userId);

        UserData::saveData($userId, 'background_mode', 'color');

        return back();
    }


    //Show custom theme
    public function showTheme(request $request)
    {
        $userId = $this->activeEditorUserId($request);
        $canUseBackgroundImage = $this->ownerCanAccessFeature('design.background_image', $request);
        $backgroundImageRequiredTierLabel = $this->requiredTierLabelForFeature('design.background_image', 'Basic');

        $data['pages'] = User::where('id', $userId)->select('littlelink_name', 'littlelink_description', 'name', 'theme')->get();
        $data['canUseBackgroundImage'] = $canUseBackgroundImage;
        $data['backgroundImageRequiredTierLabel'] = $backgroundImageRequiredTierLabel;

        return view('/studio/theme', $data);
    }

    //Save custom theme
    public function editTheme(request $request)
    {
        $userId = $this->activeEditorUserId($request);
        $templateCatalog = app(TemplateCatalogService::class);

        $requestedTemplateId = is_string($request->input('template_id'))
            ? trim((string) $request->input('template_id'))
            : '';
        $legacyThemeInput = is_string($request->input('theme'))
            ? trim((string) $request->input('theme'))
            : '';
        $requestedVariantId = is_string($request->input('variant_id'))
            ? trim((string) $request->input('variant_id'))
            : null;

        $template = null;
        if ($requestedTemplateId !== '') {
            $template = $templateCatalog->template($requestedTemplateId);
        } elseif ($legacyThemeInput !== '') {
            $template = $templateCatalog->templateByTheme($legacyThemeInput);
        }

        if (!$template) {
            return Redirect('/studio/theme')->withErrors([
                'template_id' => 'Invalid template selection.',
            ]);
        }

        $theme = (string) ($template['theme'] ?? 'default');
        $resolvedVariantId = $templateCatalog->normalizeVariantId((string) $template['id'], $requestedVariantId);

        User::where('id', $userId)->update(['theme' => $theme]);
        UserData::saveData($userId, 'template', (string) $template['id']);
        UserData::saveData($userId, 'theme_template_id', (string) $template['id']);
        UserData::saveData($userId, 'theme_variant_id', (string) ($resolvedVariantId ?? $template['default_variant_id'] ?? 'default'));

        if ($theme !== 'default') {
            UserData::saveData($userId, 'background_mode', 'template');
            UserData::saveData($userId, 'template_background_mode', 'default');
        } else {
            $currentMode = UserData::getData($userId, 'background_mode');
            if ($currentMode === 'template' || $currentMode === null || $currentMode === 'null') {
                UserData::saveData($userId, 'background_mode', 'color');
            }
        }

        return Redirect('/studio/theme')->with("success", "");
    }

    // Show and edit profile header
    public function showHeader(Request $request)
    {
        $canEditHeader = $this->ownerCanAccessFeature('design.header_image', $request);
        $requiredTierLabel = $this->requiredTierLabelForFeature('design.header_image', 'Basic');

        $userId = $this->activeEditorUserId($request);
        $mediaStorage = app(MediaStorageService::class);

        $data['pages'] = User::where('id', $userId)->select('littlelink_name', 'littlelink_description', 'name', 'theme')->get();

        $rawHeaderEnabled = UserData::getData($userId, 'header_enabled');
        $headerEnabled = !in_array($rawHeaderEnabled, [null, 'null', false, 'false', 0, '0'], true);
        $rawHeaderGradientEnabled = UserData::getData($userId, 'header_gradient_enabled');
        $headerGradientEnabled = !in_array($rawHeaderGradientEnabled, [null, 'null', false, 'false', 0, '0'], true);
        $rawHeaderHeroEnabled = UserData::getData($userId, 'header_hero_enabled');
        $headerHeroEnabled = !in_array($rawHeaderHeroEnabled, [null, 'null', false, 'false', 0, '0'], true);
        $headerPath = UserData::getData($userId, 'header_image');
        $hasHeaderPath = is_string($headerPath) && $headerPath !== 'null' && $mediaStorage->exists($headerPath);

        $data['headerSettings'] = [
            'enabled' => $headerEnabled && $hasHeaderPath,
            'gradient_enabled' => $headerGradientEnabled && $headerEnabled && $hasHeaderPath,
            'hero_enabled' => $headerHeroEnabled && $headerEnabled && $hasHeaderPath,
            'path' => $hasHeaderPath ? $headerPath : null,
        ];
        $data['canEditHeader'] = $canEditHeader;
        $data['requiredTierLabel'] = $requiredTierLabel;

        return view('/studio/header', $data);
    }

    public function editHeader(Request $request)
    {
        if (!$this->ownerCanAccessFeature('design.header_image', $request)) {
            $requiredTierLabel = $this->requiredTierLabelForFeature('design.header_image', 'Basic');

            return redirect('/dashboard/subscription')
                ->withErrors("Verfügbar ab {$requiredTierLabel}.");
        }

        $userId = $this->activeEditorUserId($request);
        $mediaStorage = app(MediaStorageService::class);
        $headerLimit = $this->imageUploadLimit('header_hero', 4096, 3000, 1200);

        $request->validate([
            'header_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:'.$headerLimit['max_kb'].'|dimensions:max_width='.$headerLimit['max_width'].',max_height='.$headerLimit['max_height'],
            'enable_header' => 'nullable|boolean',
            'enable_header_gradient' => 'nullable|boolean',
            'enable_header_hero' => 'nullable|boolean',
            'remove_header_image' => 'nullable|boolean',
        ], [
            'header_image.image' => __('messages.The selected file must be an image'),
            'header_image.mimes' => __('messages.The image must be') . ' JPEG, JPG, PNG, webP.',
            'header_image.max' => __('messages.upload.validation.max', [
                'label' => __('messages.Hero/header image'),
                'size' => $headerLimit['max_mb'],
            ]),
            'header_image.dimensions' => __('messages.upload.validation.dimensions', [
                'label' => __('messages.Hero/header image'),
                'width' => $headerLimit['max_width'],
                'height' => $headerLimit['max_height'],
            ]),
        ]);

        $headerFile = $request->file('header_image');
        $enableHeader = $request->boolean('enable_header');
        $enableHeaderGradient = $request->boolean('enable_header_gradient');
        $enableHeaderHero = $request->boolean('enable_header_hero');
        $removeHeaderImage = $request->boolean('remove_header_image');
        $rawProfileHeaderLayout = UserData::getData($userId, 'profile_header_layout');
        $profileHeaderLayout = is_string($rawProfileHeaderLayout)
            ? strtolower(trim($rawProfileHeaderLayout))
            : 'standard';
        $heroOnlyLayouts = ['business', 'business_header_focus_description'];
        $businessLayoutActive = in_array($profileHeaderLayout, $heroOnlyLayouts, true);

        // In business layout, only hero mode is allowed for the header image area.
        if ($businessLayoutActive) {
            $enableHeader = false;
        }

        // Outside business layout, Header and Hero are mutually exclusive; Header wins.
        if (!$businessLayoutActive && $enableHeader && $enableHeaderHero) {
            $enableHeaderHero = false;
        }

        $currentHeaderPath = UserData::getData($userId, 'header_image');
        $storedHeaderPath = ($currentHeaderPath !== null && $currentHeaderPath !== "null") ? $currentHeaderPath : null;

        if ($removeHeaderImage && $storedHeaderPath) {
            $mediaStorage->delete($storedHeaderPath);
            UserData::removeData($userId, 'header_image');
            $storedHeaderPath = null;
        }

        if ($headerFile) {
            if ($storedHeaderPath) {
                $mediaStorage->delete($storedHeaderPath);
            }

            $storedHeaderPath = $mediaStorage->storeUploadedFile($userId, $headerFile, 'headers');
            UserData::saveData($userId, 'header_image', $storedHeaderPath);
        }

        $hasHeaderFile = $storedHeaderPath && $mediaStorage->exists($storedHeaderPath);
        $shouldEnable = ($enableHeader || $enableHeaderHero) && $hasHeaderFile && !$removeHeaderImage;
        $shouldEnableHero = $enableHeaderHero && $shouldEnable;
        $shouldEnableGradient = $enableHeaderGradient && $shouldEnable;

        UserData::saveData($userId, 'header_enabled', $shouldEnable);
        UserData::saveData($userId, 'header_gradient_enabled', $shouldEnableGradient);
        UserData::saveData($userId, 'header_hero_enabled', $shouldEnableHero);

        if (!$hasHeaderFile) {
            UserData::saveData($userId, 'header_enabled', false);
            UserData::saveData($userId, 'header_gradient_enabled', false);
            UserData::saveData($userId, 'header_hero_enabled', false);
        }

        return redirect('/studio/header');
    }

    //Show user (name, email, password)
    public function showProfile(Request $request, TwoFactorService $twoFactorService)
    {
        $user = Auth::user();
        $userId = $user->id;

        $data['profile'] = User::where('id', $userId)->select('name', 'email', 'role')->get();

        // MODULE: Surface tier/subscription details in profile
        /** @var SubscriptionManager $subscriptionManager */
        $subscriptionManager = app(SubscriptionManager::class);
        $tier = $subscriptionManager->getUserTier($user);
        $subscription = UserSubscription::with('tier')->where('user_id', $userId)->first();

        $expired = $subscriptionManager->isExpired($user);
        $inGrace = $subscriptionManager->isInGracePeriod($user);
        $status = 'Free';
        if ($tier) {
            $status = $expired ? ($inGrace ? 'In grace period' : 'Expired') : 'Active';
        }

        $twoFactorEnabled = (bool) $user->two_factor_enabled;
        $twoFactorSecret = session('two_factor_secret');

        if (!$twoFactorSecret && !$twoFactorEnabled) {
            $twoFactorSecret = $user->decryptedTwoFactorSecret();
        }

        $twoFactorRecoveryCodes = session('two_factor_recovery_codes', []);
        if (empty($twoFactorRecoveryCodes) && !$twoFactorEnabled && $user->two_factor_recovery_codes) {
            $twoFactorRecoveryCodes = $user->recoveryCodes();
        }

        $otpAuthUrl = $twoFactorSecret ? $twoFactorService->getOtpAuthUrl($user->email, $twoFactorSecret, config('app.name')) : null;
        $twoFactorQr = $otpAuthUrl ? QrCode::size(180)->generate($otpAuthUrl) : null;

        $data['tierInfo'] = [
            'name' => $tier->name ?? 'Free',
            'slug' => $tier->slug ?? 'free',
            'status' => $status,
            'expires_at' => $subscription?->expires_at,
        ];

        $data['twoFactorEnabled'] = $twoFactorEnabled;
        $data['twoFactorSecret'] = $twoFactorSecret;
        $data['twoFactorRecoveryCodes'] = $twoFactorRecoveryCodes;
        $data['twoFactorQr'] = $twoFactorQr;
        $data['pendingEmail'] = $user->pending_email;
        $data['supportedLocales'] = config('app.supported_locales', []);
        $data['userLocale'] = $user->locale;

        return view('/studio/profile', $data);
    }

    //Save user (name, email, password)
    public function editProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
        ]);

        if ($request->filled('name')) {
            User::where('id', Auth::id())->update(['name' => trim(strip_tags((string) $request->name))]);
            return back()->with('success', __('messages.Profile updated'));
        }

        return back();
    }

    public function updateLocale(Request $request)
    {
        $supportedLocales = (array) config('app.supported_locales', []);
        $locale = $request->input('locale');
        $locale = $locale === '' ? null : $locale;

        $request->merge(['locale' => $locale]);

        $request->validate([
            'locale' => ['nullable', 'string', Rule::in($supportedLocales)],
        ]);

        User::where('id', Auth::id())->update(['locale' => $locale]);

        return back()->with('success', __('messages.Language updated successfully'));
    }

    public function updatePageLocale(Request $request)
    {
        $supportedLocales = (array) config('app.supported_locales', []);
        $locale = $request->input('locale');
        $locale = $locale === '' ? null : $locale;
        $targetUserId = $this->activeEditorUserId($request);

        $request->merge(['locale' => $locale]);

        $request->validate([
            'locale' => ['nullable', 'string', Rule::in($supportedLocales)],
        ]);

        User::where('id', $targetUserId)->update(['locale' => $locale]);

        return back()->with('success', __('messages.Language updated successfully'));
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        Auth::logoutOtherDevices($request->password);
        $request->session()->regenerate();

        try {
            $user->notify(new PasswordChangedNotification());
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', __('messages.Password updated successfully'));
    }

    public function requestEmailChange(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'new_email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
        ]);

        $newEmail = trim((string) $request->input('new_email'));

        if ($newEmail === $user->email) {
            return back()->withErrors(['new_email' => __('messages.The new email must be different')]);
        }

        $token = Str::random(60);
        $originalPendingEmail = $user->pending_email;
        $originalPendingEmailToken = $user->pending_email_token;

        $user->forceFill([
            'pending_email' => $newEmail,
            'pending_email_token' => hash('sha256', $token),
        ])->save();

        $signedUrl = URL::temporarySignedRoute('profile.email.confirm', now()->addMinutes(60), [
            'user' => $user->id,
            'email' => $newEmail,
            'token' => $token,
        ]);
        $emailLocale = $user->preferredLocale();

        try {
            Notification::route('mail', $newEmail)->notify(new EmailChangeVerification($signedUrl, $newEmail, $emailLocale));
        } catch (\Throwable $e) {
            report($e);

            $user->forceFill([
                'pending_email' => $originalPendingEmail,
                'pending_email_token' => $originalPendingEmailToken,
            ])->save();

            return back()->withErrors([
                'new_email' => __('messages.We could not send a verification link to the new email address'),
            ]);
        }

        try {
            $user->notify(new EmailChangeRequested($newEmail));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', __('messages.We sent a verification link to your new email address'));
    }

    public function confirmEmailChange(Request $request, $user)
    {
        $user = User::findOrFail($user);

        if (! $request->hasValidSignature()) {
            return redirect()->route('login')->withErrors([
                'email' => __('messages.Invalid or expired email change link'),
            ]);
        }

        $newEmail = $request->query('email');
        $token = $request->query('token');

        if (!$newEmail || !$token || !$user->pending_email || !$user->pending_email_token) {
            return redirect()->route('login')->withErrors([
                'email' => __('messages.Invalid or expired email change link'),
            ]);
        }

        if (!hash_equals($user->pending_email, $newEmail) || !hash_equals($user->pending_email_token, hash('sha256', $token))) {
            return redirect()->route('login')->withErrors([
                'email' => __('messages.Invalid or expired email change link'),
            ]);
        }

        if (User::where('email', $newEmail)->where('id', '!=', $user->id)->exists()) {
            return redirect()->route('login')->withErrors([
                'email' => __('messages.Email already in use'),
            ]);
        }

        $oldEmail = $user->email;
        $emailLocale = $user->preferredLocale();

        $user->forceFill([
            'email' => $newEmail,
            'pending_email' => null,
            'pending_email_token' => null,
            'email_verified_at' => now(),
        ])->save();

        try {
            Notification::route('mail', $oldEmail)->notify(new EmailChanged($oldEmail, $newEmail, $emailLocale));
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            Notification::route('mail', $newEmail)->notify(new EmailChanged($oldEmail, $newEmail, $emailLocale));
        } catch (\Throwable $e) {
            report($e);
        }

        if (Auth::check() && Auth::id() === $user->id) {
            $request->session()->put('two_factor_user_id', $user->id);
        }

        if (Auth::check() && Auth::id() === $user->id) {
            return redirect()->route('showProfile')->with('success', __('messages.Email updated successfully'));
        }

        return redirect()->route('login')->with('status', __('messages.Email updated successfully'));
    }

    //Show user theme credit page
    public function theme(request $request)
    {
        $littlelink_name = $request->littlelink;
        $id = User::select('id')->where('littlelink_name', $littlelink_name)->value('id');

        if (empty($id)) {
            return abort(404);
        }

        $userinfo = User::select('name', 'littlelink_name', 'littlelink_description', 'theme')->where('id', $id)->first();
        $information = User::select('name', 'littlelink_name', 'littlelink_description', 'theme')->where('id', $id)->get();

        $links = DB::table('links')
            ->join('buttons', 'buttons.id', '=', 'links.button_id')
            ->select('links.link', 'links.id', 'links.button_id', 'links.title', 'links.custom_css', 'links.custom_icon', 'buttons.name')
            ->where('user_id', $id)
            ->when(Schema::hasColumn('links', 'is_disabled'), function ($q) {
                $q->where('links.is_disabled', false);
            })
            ->orderBy('up_link', 'asc')
            ->orderBy('order', 'asc')
            ->get();

        return view('components/theme', ['userinfo' => $userinfo, 'information' => $information, 'links' => $links, 'littlelink_name' => $littlelink_name]);
    }

    //Delete existing user
    public function deleteUser(
        Request $request,
        TwoFactorService $twoFactorService,
        AccountDeletionService $accountDeletionService
    )
    {
        $user = $request->user();
        $targetUserId = (int) $request->route('id');

        if (! $user || (int) $user->id !== $targetUserId) {
            return back()->withErrors([
                'delete_account' => __('messages.You may only delete your own account'),
            ]);
        }

        if ((int) $user->id === 1) {
            return back()->withErrors([
                'delete_account' => __('messages.This account cannot be deleted'),
            ]);
        }

        $request->merge([
            'delete_confirmation' => strtoupper(trim((string) $request->input('delete_confirmation'))),
        ]);

        $rules = [
            'delete_confirmation' => ['required', 'in:DELETE'],
            'current_password' => ['required', 'current_password'],
        ];

        if ($user->two_factor_enabled) {
            $rules['delete_2fa_code'] = ['required', 'string'];
        }

        $request->validate($rules);

        if (
            $user->two_factor_enabled
            && ! $this->validateDestructiveTwoFactorCode(
                $user,
                (string) $request->input('delete_2fa_code'),
                $twoFactorService
            )
        ) {
            return back()
                ->withInput($request->only('delete_confirmation'))
                ->withErrors([
                    'delete_2fa_code' => __('messages.Invalid two-factor code'),
                ]);
        }

        try {
            $accountDeletionService->deleteUser($user, [
                'source' => 'self_delete',
                'actor_user_id' => (int) $user->id,
                'deletion_reason' => 'self',
            ]);
        } catch (AccountDeletionBlockedException $e) {
            report($e);

            return back()
                ->withInput($request->only('delete_confirmation'))
                ->withErrors([
                    'delete_account' => __('messages.We could not delete your account right now'),
                ]);
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput($request->only('delete_confirmation'))
                ->withErrors([
                    'delete_account' => __('messages.We could not delete your account right now'),
                ]);
        }

        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->forget(['two_factor_passed', 'two_factor_user_id']);

        return redirect()->route('login')->with('status', __('Your account has been deleted.'));
    }

    protected function validateDestructiveTwoFactorCode(User $user, string $code, TwoFactorService $service): bool
    {
        $normalizedCode = strtoupper(trim($code));
        if ($normalizedCode === '') {
            return false;
        }

        $secret = $user->decryptedTwoFactorSecret();
        if ($secret && $service->verify($secret, $normalizedCode)) {
            return true;
        }

        $recoveryCodes = $user->recoveryCodes();
        if (empty($recoveryCodes)) {
            return false;
        }

        foreach ($recoveryCodes as $index => $storedCode) {
            if (! hash_equals($storedCode, $normalizedCode)) {
                continue;
            }

            unset($recoveryCodes[$index]);
            $user->forceFill([
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($recoveryCodes))),
            ])->save();

            return true;
        }

        return false;
    }

    //Delete profile picture
    public function delProfilePicture()
    {
        $userId = $this->activeEditorUserId();
        app(MediaStorageService::class)->deleteAvatarForUser($userId);

        return back();
    }

    //Export user links
    public function exportLinks(request $request)
    {
        $userId = $this->activeEditorUserId($request);
        $user = User::find($userId);
        $links = Link::where('user_id', $userId)->get();
        
        if (!$user) {
            // handle the case where the user is null
            return response()->json(['message' => 'User not found'], 404);
        }

        $userData['links'] = $links->toArray();

        $domain = $_SERVER['HTTP_HOST'];
        $date = date('Y-m-d_H-i-s');
        $fileName = "links-$domain-$date.json";
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];
        return response()->json($userData, 200, $headers);

        return back();
    }

    public function exportAll(Request $request)
    {
        $userId = $this->activeEditorUserId($request);
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Whitelist: only user-facing fields – never expose credentials or internal system state.
        $profileFields = [
            'id', 'name', 'email', 'email_verified_at',
            'littlelink_name', 'littlelink_description',
            'theme', 'locale', 'is_published', 'published_at',
            'provider',
            'tos_accepted_at', 'tos_version', 'tos_content_hash',
            'agb_accepted_at', 'agb_version', 'agb_content_hash',
            'avv_accepted_at', 'avv_version', 'avv_content_hash',
            'created_at', 'updated_at',
        ];
        $profile = array_intersect_key($user->toArray(), array_flip($profileFields));

        $links = Link::where('user_id', $userId)
            ->select(['id', 'user_id', 'link', 'title', 'button_order', 'custom_css', 'custom_js', 'created_at', 'updated_at'])
            ->get()
            ->toArray();

        $legalAcceptances = \DB::table('user_agreement_acceptances')
            ->where('user_id', $userId)
            ->select(['agreement_type', 'version', 'content_hash', 'accepted_at', 'source', 'ip_address'])
            ->orderBy('accepted_at')
            ->get()
            ->toArray();

        $auditLog = \DB::table('compliance_audit_log')
            ->where('user_id', $userId)
            ->select(['event_type', 'status', 'source', 'created_at'])
            ->orderBy('created_at')
            ->get()
            ->toArray();

        $export = [
            'export_generated_at' => now()->toIso8601String(),
            'profile' => $profile,
            'links' => $links,
            'legal_acceptances' => $legalAcceptances,
            'compliance_audit_log' => $auditLog,
            'note_forms' => 'Form submissions are available for export in the Forms dashboard.',
        ];

        $mediaStorage = app(MediaStorageService::class);
        $avatarPath = $mediaStorage->avatarPathForUser($userId);
        if ($avatarPath !== null) {
            $binary = $mediaStorage->binaryContents($avatarPath);
            if (is_string($binary)) {
                $export['avatar_base64'] = base64_encode($binary);
                $imageExtension = strtolower((string) pathinfo($avatarPath, PATHINFO_EXTENSION));
                if ($imageExtension !== '') {
                    $export['avatar_extension'] = $imageExtension;
                }
            }
        }

        $domain = $_SERVER['HTTP_HOST'];
        $date = date('Y-m-d_H-i-s');
        $fileName = "user_data-$domain-$date.json";
        return response()->json($export, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }    

    public function importData(Request $request)
    {
        // Legacy import is permanently disabled and intentionally unreachable in production.
        abort(410, 'Profile import has been permanently disabled.');
    }
    

    public function showReportForm(Request $request)
    {
        $reportUser = null;
        $requestedId = $request->query('id');
        $requestedUserId = is_numeric($requestedId) ? (int) $requestedId : null;

        if (!$this->isCanonicalAppHost($request->getHost())) {
            return redirect()->to(wayvioReportUrl($requestedUserId));
        }

        if ($requestedUserId !== null) {
            $reportUser = User::query()->select('id', 'littlelink_name')->find($requestedUserId);
        }

        return view('report', [
            'reportUserId' => $reportUser?->id,
            'reportPageUrl' => $reportUser ? $this->publicProfileUrlForUser($reportUser) : null,
        ]);
    }

    private function isCanonicalAppHost(string $host): bool
    {
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        return $appHost === '' || strtolower($host) === $appHost;
    }

    // Handle reports
    public function report(Request $request, CaptchaVerifier $captchaVerifier)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'url' => 'required|url|max:2048',
            'report-type' => ['required', 'string', Rule::in($this->reportTypeOptions())],
            'message' => 'nullable|string|max:1000',
        ]);

        if (config('reports.captcha_required', false)) {
            $captchaVerifier->validate($request, CaptchaVerifier::CONTEXT_REPORT);
        } else {
            $captchaVerifier->validateIfConfigured($request, CaptchaVerifier::CONTEXT_REPORT);
        }

        $urlScheme = strtolower((string) parse_url($validated['url'], PHP_URL_SCHEME));
        if (!in_array($urlScheme, ['http', 'https'], true)) {
            return redirect()->to(wayvioReportUrl())->with('error', __('messages.report_error'));
        }

        $reportedUser = $this->resolveReportedUserFromInput(
            $validated['url'],
            isset($validated['id']) ? (int) $validated['id'] : null,
            $request->getHost()
        );

        if (!$reportedUser) {
            \Log::info('Report rejected: target could not be resolved', [
                'host' => $request->getHost(),
                'url_host' => parse_url($validated['url'], PHP_URL_HOST),
                'input_user_id' => $validated['id'] ?? null,
            ]);

            return redirect()->to(wayvioReportUrl())->with('error', __('messages.report_error'));
        }

        if (!config('reports.allow_self_report', false) && Auth::check() && (int) Auth::id() === (int) $reportedUser->id) {
            return redirect()->to(wayvioReportUrl((int) $reportedUser->id))->with('error', __('messages.report_self_error'));
        }

        if (!$this->consumeTargetRateLimit($request, $reportedUser->id)) {
            return redirect()->to(wayvioReportUrl((int) $reportedUser->id))
                ->with('error', __('messages.report_rate_limit_error'))
                ->setStatusCode(429);
        }

        try {
            $this->storeOrIncrementReport($request, $reportedUser, $validated);
            return redirect()->to(wayvioReportUrl((int) $reportedUser->id))->with('success', __('messages.report_success'));
        } catch (\Throwable $e) {
            // Keep reporting non-blocking for users while storing failures in logs.
            \Log::warning('Report storage failed: ' . $e->getMessage());
            return redirect()->to(wayvioReportUrl((int) $reportedUser->id))->with('success', __('messages.report_success'));
        }
    }

    private function consumeTargetRateLimit(Request $request, int $reportedUserId): bool
    {
        $targetLimitPerHour = max(1, (int) config('reports.rate_limit_target_per_hour', 3));
        $key = sprintf('report-target:%s:%s', $request->ip(), $reportedUserId);

        if (RateLimiter::tooManyAttempts($key, $targetLimitPerHour)) {
            return false;
        }

        RateLimiter::hit($key, 3600);
        return true;
    }

    private function storeOrIncrementReport(Request $request, User $reportedUser, array $validated): void
    {
        $now = now();
        $reporterId = Auth::id();
        $ipHash = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key', 'report-ip-fallback-key'));
        $reportSnapshotUrl = $this->publicProfileUrlForUser($reportedUser);
        $reportSnapshotName = $reportedUser->littlelink_name ?: ('user-' . $reportedUser->id);
        $incomingMessage = $validated['message'] ?? null;

        $createOrUpdate = function () use ($now, $reporterId, $ipHash, $reportSnapshotUrl, $reportSnapshotName, $reportedUser, $validated, $incomingMessage): void {
            $report = PageReport::withTrashed()
                ->where('reported_user_id', $reportedUser->id)
                ->lockForUpdate()
                ->first();

            if ($report) {
                if ($report->trashed()) {
                    $report->restore();
                }

                $report->report_count = max(1, (int) $report->report_count) + 1;
                $report->last_reported_at = $now;
                $report->reported_page_name_snapshot = $reportSnapshotName;
                $report->reported_page_url_snapshot = $reportSnapshotUrl;
                $report->last_report_type = $validated['report-type'];
                if ($incomingMessage !== null) {
                    $report->last_report_message = $incomingMessage;
                }
                $report->last_reporter_user_id = $reporterId;
                $report->last_reporter_ip_hash = $ipHash;
                $report->status = PageReport::STATUS_OPEN;
                $report->processed_at = null;
                $report->processed_by_user_id = null;
                $report->save();
                $this->recordReportEvent($report, $reporterId, $ipHash, $validated['report-type'], $incomingMessage);
                return;
            }

            $report = PageReport::create([
                'reported_user_id' => $reportedUser->id,
                'reported_page_name_snapshot' => $reportSnapshotName,
                'reported_page_url_snapshot' => $reportSnapshotUrl,
                'report_count' => 1,
                'first_reported_at' => $now,
                'last_reported_at' => $now,
                'last_report_type' => $validated['report-type'],
                'last_report_message' => $incomingMessage,
                'last_reporter_user_id' => $reporterId,
                'last_reporter_ip_hash' => $ipHash,
                'status' => PageReport::STATUS_OPEN,
            ]);
            $this->recordReportEvent($report, $reporterId, $ipHash, $validated['report-type'], $incomingMessage);
        };

        try {
            DB::transaction($createOrUpdate, 3);
        } catch (QueryException $e) {
            if (!$this->isDuplicateReportInsertException($e)) {
                throw $e;
            }

            DB::transaction(function () use ($now, $reporterId, $ipHash, $reportSnapshotUrl, $reportSnapshotName, $reportedUser, $validated, $incomingMessage, $e): void {
                $report = PageReport::withTrashed()
                    ->where('reported_user_id', $reportedUser->id)
                    ->lockForUpdate()
                    ->first();

                if (!$report) {
                    throw $e;
                }

                if ($report->trashed()) {
                    $report->restore();
                }

                $report->report_count = max(1, (int) $report->report_count) + 1;
                $report->last_reported_at = $now;
                $report->reported_page_name_snapshot = $reportSnapshotName;
                $report->reported_page_url_snapshot = $reportSnapshotUrl;
                $report->last_report_type = $validated['report-type'];
                if ($incomingMessage !== null) {
                    $report->last_report_message = $incomingMessage;
                }
                $report->last_reporter_user_id = $reporterId;
                $report->last_reporter_ip_hash = $ipHash;
                $report->status = PageReport::STATUS_OPEN;
                $report->processed_at = null;
                $report->processed_by_user_id = null;
                $report->save();
                $this->recordReportEvent($report, $reporterId, $ipHash, $validated['report-type'], $incomingMessage);
            }, 3);
        }
    }

    private function recordReportEvent(PageReport $report, ?int $reporterId, ?string $ipHash, ?string $reportType, ?string $message): void
    {
        if (!Schema::hasTable('page_report_events')) {
            return;
        }

        PageReportEvent::create([
            'page_report_id' => $report->id,
            'reporter_user_id' => $reporterId,
            'reporter_ip_hash' => $ipHash,
            'report_type' => $reportType,
            'message' => $message,
        ]);
    }

    private function isDuplicateReportInsertException(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        return in_array($sqlState, ['23000', '23505'], true);
    }

    private function resolveReportedUserFromInput(string $url, ?int $inputUserId, string $requestHost): ?User
    {
        $userFromId = null;
        if ($inputUserId) {
            $userFromId = User::query()->select('id', 'littlelink_name')->find($inputUserId);
        }

        $userFromUrl = $this->resolveReportedUserByUrl($url, $requestHost);
        if ($userFromUrl && $userFromId && (int) $userFromUrl->id !== (int) $userFromId->id) {
            return null;
        }

        return $userFromUrl ?: $userFromId;
    }

    private function resolveReportedUserByUrl(string $url, string $requestHost): ?User
    {
        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            return null;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host === '') {
            return null;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $allowedHosts = array_filter(array_unique([$appHost, strtolower($requestHost)]));

        if (in_array($host, $allowedHosts, true)) {
            $path = (string) ($parsed['path'] ?? '');
            return $this->resolveReportTargetFromPath($path);
        }

        if (!Schema::hasTable('user_custom_domains')) {
            return null;
        }

        $mapping = DB::table('user_custom_domains')
            ->whereRaw('LOWER(domain) = ?', [$host])
            ->where('status', 'verified')
            ->when(Schema::hasColumn('user_custom_domains', 'lifecycle_status'), function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->whereNull('lifecycle_status')
                        ->orWhere('lifecycle_status', 'active');
                });
            })
            ->select('page_id', 'user_id')
            ->first();

        return $this->resolveUserFromDomainMapping($mapping, (string) ($parsed['path'] ?? ''));
    }

    private function resolveReportTargetFromPath(string $path): ?User
    {
        $normalizedPath = trim($path, '/');
        if ($normalizedPath === '') {
            return null;
        }

        if (preg_match('/^u\/(\d+)$/', $normalizedPath, $matches) === 1) {
            return User::query()->select('id', 'littlelink_name')->find((int) $matches[1]);
        }

        if (preg_match('/^p\/([A-Za-z0-9._-]+)$/', $normalizedPath, $matches) === 1) {
            return $this->findUserBySlug($matches[1]);
        }

        if (preg_match('/^@([A-Za-z0-9._-]+)$/', $normalizedPath, $matches) === 1) {
            return $this->findUserBySlug($matches[1]);
        }

        $customPrefix = (string) config('advanced-config.custom_url_prefix', '');
        $customPrefix = ltrim($customPrefix, '/');

        if ($customPrefix !== '' && str_starts_with($normalizedPath, $customPrefix)) {
            $slug = ltrim(substr($normalizedPath, strlen($customPrefix)), '/');
            if ($this->isValidReportSlug($slug)) {
                return $this->findUserBySlug($slug);
            }
        }

        $firstSegment = explode('/', $normalizedPath)[0] ?? '';
        if (!$this->isValidReportSlug($firstSegment)) {
            return null;
        }
        if (in_array($firstSegment, reservedSlugs(), true)) {
            return null;
        }

        return $this->findUserBySlug($firstSegment);
    }

    private function isValidReportSlug(string $slug): bool
    {
        return $slug !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $slug) === 1;
    }

    private function findUserBySlug(string $slug): ?User
    {
        return User::query()
            ->select('id', 'littlelink_name')
            ->where('littlelink_name', $slug)
            ->first();
    }

    private function resolveUserFromDomainMapping(?object $mapping, string $path = ''): ?User
    {
        if (!$mapping) {
            return null;
        }

        $pageId = (int) ($mapping->page_id ?? 0);
        if ($pageId > 0) {
            return User::query()->select('id', 'littlelink_name')->find($pageId);
        }

        $ownerId = (int) ($mapping->user_id ?? 0);
        if ($ownerId <= 0) {
            return null;
        }

        $owner = User::query()->select('id', 'littlelink_name')->find($ownerId);
        if (!$owner) {
            return null;
        }

        $normalizedPath = trim($path, '/');
        if ($normalizedPath === '') {
            return $owner;
        }

        if (!Schema::hasTable('agency_hubs')) {
            return $owner;
        }

        if ($normalizedPath === 'p') {
            return null;
        }

        $slug = null;
        if (preg_match('/^p\/([A-Za-z0-9._-]+)$/', $normalizedPath, $matches) === 1) {
            $slug = $matches[1];
        } elseif (preg_match('/^([A-Za-z0-9._-]+)(?:\/(imprint|impressum|privacy|datenschutz|datenschutzerklaerung))?$/', $normalizedPath, $matches) === 1) {
            $slug = $matches[1];
        }

        if (!$slug) {
            return null;
        }

        $ownerSlug = (string) User::query()
            ->where('id', $ownerId)
            ->value('littlelink_name');
        if ($ownerSlug !== '' && $slug === $ownerSlug) {
            return User::query()->select('id', 'littlelink_name')->find($ownerId);
        }

        $managedUserId = AgencyHub::query()
            ->where('agency_user_id', $ownerId)
            ->where('status', 'active')
            ->whereHas('managedUser', function ($query) use ($slug): void {
                $query->where('littlelink_name', $slug);
            })
            ->value('managed_user_id');

        if ($managedUserId) {
            return User::query()->select('id', 'littlelink_name')->find((int) $managedUserId);
        }

        return null;
    }

    private function customLinkIconPresets(): array
    {
        return [
            '',
            'fa-external-link',
            'fa-link',
            'fa-globe',
            'fa-user',
            'fa-envelope',
            'fa-phone',
            'fa-comment',
            'fa-play',
            'fa-music',
            'fa-camera',
            'fa-video-camera',
            'fa-shopping-cart',
            'fa-credit-card',
            'fa-heart',
            'fa-star',
            'fa-book',
            'fa-newspaper',
            'fa-calendar',
            'fa-map-marker',
            'fa-download',
            'fa-file-lines',
            'fa-rocket',
            'fa-lightbulb',
            'fa-briefcase',
            'fa-graduation-cap',
            'fa-gamepad',
            'fa-headphones',
            'fa-code',
            'fa-share-alt',
            'fa-info-circle',
            'fa-question-circle',
            'fa-lock',
        ];
    }

    private function normalizeCustomLinkIcon(string $icon): ?string
    {
        $icon = strtolower(trim($icon));
        $legacyMap = [
            'fa-newspaper-o' => 'fa-newspaper',
            'fa-file-text-o' => 'fa-file-lines',
            'fa-lightbulb-o' => 'fa-lightbulb',
        ];

        if ($icon === '') {
            return '';
        }

        if (array_key_exists($icon, $legacyMap)) {
            $icon = $legacyMap[$icon];
        }

        if (in_array($icon, $this->customLinkIconPresets(), true)) {
            return $icon;
        }

        return null;
    }

    private function publicProfileUrlForUser(User $user): string
    {
        $resolver = app(DomainUrlResolver::class);
        $owner = $resolver->ownerForPageUser($user);

        return $resolver->profileUrlForEditor($owner, $user);
    }

    private function reportTypeOptions(): array
    {
        return [
            __('messages.hate_speech'),
            __('messages.violence_threats'),
            __('messages.illegal_activities'),
            __('messages.copyright_infringement'),
            __('messages.misinformation_fake_news'),
            __('messages.identity_theft'),
            __('messages.drug_related_content'),
            __('messages.weapons_harmful_objects'),
            __('messages.child_exploitation'),
            __('messages.fraud_scams'),
            __('messages.privacy_violation'),
            __('messages.impersonation'),
            __('messages.other_specify'),
        ];
    }

    private function publicPageUserQuery()
    {
        $query = User::query()
            ->select('id', 'name', 'littlelink_name', 'littlelink_description', 'theme', 'role', 'block', 'meta_overrides');

        if (Schema::hasColumn('users', 'locale')) {
            $query->addSelect('locale');
        }

        if (Schema::hasColumn('users', 'last_login_locale')) {
            $query->addSelect('last_login_locale');
        }

        if (Schema::hasColumn('users', 'is_published')) {
            $query->addSelect('is_published');
        }

        if (Schema::hasColumn('users', 'published_at')) {
            $query->addSelect('published_at');
        }

        if (Schema::hasColumn('users', 'meta_tags_status')) {
            $query->addSelect('meta_tags_status');
        }

        if (Schema::hasColumn('users', 'meta_tags_status_reason')) {
            $query->addSelect('meta_tags_status_reason');
        }

        return $query;
    }

    private function canRenderPublishedHub(User $pageUser, ?User $viewer): bool
    {
        return app(HubPublicationService::class)->canRenderPublic($pageUser, $viewer);
    }

    private function renderUnpublishedPageNotAvailable()
    {
        return response()->view('wayvio.not-available', [], 404);
    }

    //Edit/save page icons
    public function editIcons(Request $request)
    {
        $inputKeys = array_keys($request->except('_token'));

        $validationRules = [];

        foreach ($inputKeys as $platform) {
            $validationRules[$platform] = 'nullable|exturl|max:255';
        }

        $request->validate($validationRules);

        foreach ($inputKeys as $platform) {
            $link = $request->input($platform);

            if (!empty($link)) {
                $iconId = $this->searchIcon($platform);

                if (!is_null($iconId)) {
                    $this->updateIcon($platform, $link);
                } else {
                    $this->addIcon($platform, $link);
                }
            }
        }

        return redirect('studio/links#icons');
    }

    private function searchIcon($icon)
    {
        $userId = $this->activeEditorUserId();

        return DB::table('links')
            ->where('user_id', $userId)
            ->where('title', $icon)
            ->where('button_id', 94)
            ->when(Schema::hasColumn('links', 'is_disabled'), function ($q) {
                $q->where('is_disabled', false);
            })
            ->value('id');
    }

    private function addIcon($icon, $link)
    {
        $userId = $this->activeEditorUserId();
        $links = new Link;
        $links->link = $link;
        $links->user_id = $userId;
        $tenantOwnerId = $this->tenantOwnerIdForEditorUser($userId);
        if ($tenantOwnerId !== null && Schema::hasColumn('links', 'tenant_owner_user_id')) {
            $links->tenant_owner_user_id = $tenantOwnerId;
        }
        $links->title = $icon;
        $links->button_id = '94';
        $links->save();
        $links->order = ($links->id - 1);
        $links->save();
    }

    private function updateIcon($icon, $link)
    {
        $updates = [
            'button_id' => 94,
            'link' => $link,
            'title' => $icon
        ];

        $tenantOwnerId = $this->tenantOwnerIdForEditorUser($this->activeEditorUserId());
        if ($tenantOwnerId !== null && Schema::hasColumn('links', 'tenant_owner_user_id')) {
            $updates['tenant_owner_user_id'] = $tenantOwnerId;
        }

        Link::where('id', $this->searchIcon($icon))->update($updates);
    }

    private function imprintFieldKeys(): array
    {
        return [
            'imprint_name',
            'imprint_legal_form',
            'imprint_represented_by',
            'imprint_street',
            'imprint_postal_code',
            'imprint_city',
            'imprint_country',
            'imprint_email',
            'imprint_phone',
            'imprint_fax',
            'imprint_supervisory_authority',
            'imprint_register_name',
            'imprint_register_court',
            'imprint_register_number',
            'imprint_professional_title',
            'imprint_professional_state',
            'imprint_chamber',
            'imprint_professional_rules',
            'imprint_vat_id',
            'imprint_business_id',
            'imprint_liquidation_notice',
            'imprint_adr_notice',
            'imprint_odr_url',
            'imprint_av_member_state',
            'imprint_av_authority',
            'imprint_additional_text',
            'imprint_contact_form_enabled',
        ];
    }

    private function firstImprintLinkForUser(int $userId): ?Link
    {
        return Link::withDisabled()
            ->where('user_id', $userId)
            ->where('type', 'imprint')
            ->orderBy('order', 'asc')
            ->orderBy('id', 'asc')
            ->first();
    }

    private function decodedTypeParams($typeParams): array
    {
        if (!is_string($typeParams) || trim($typeParams) === '') {
            return [];
        }

        $decoded = json_decode($typeParams, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function privacyStorageKey(): string
    {
        return 'legal_privacy_layer_model';
    }

    private function privacyLayer1SyncableKeys(): array
    {
        return [
            'controller_name',
            'street',
            'postal_code',
            'city',
            'country',
            'email',
            'phone',
        ];
    }

    private function privacyLayer1ImprintDefaults(array $imprintParams = [], string $privacyLocale = 'de'): array
    {
        return [
            'controller_name' => trim((string) ($imprintParams['imprint_name'] ?? '')),
            'street' => trim((string) ($imprintParams['imprint_street'] ?? '')),
            'postal_code' => trim((string) ($imprintParams['imprint_postal_code'] ?? '')),
            'city' => trim((string) ($imprintParams['imprint_city'] ?? '')),
            'country' => trim((string) ($imprintParams['imprint_country'] ?? ($privacyLocale === 'en' ? 'Germany' : 'Deutschland'))),
            'email' => trim((string) ($imprintParams['imprint_email'] ?? '')),
            'phone' => trim((string) ($imprintParams['imprint_phone'] ?? '')),
        ];
    }

    private function normalizePrivacyLayer1Sync(array $input, array $defaults): array
    {
        $normalized = [];
        foreach ($this->privacyLayer1SyncableKeys() as $key) {
            if (!array_key_exists($key, $input)) {
                $normalized[$key] = (bool) ($defaults[$key] ?? false);
                continue;
            }

            $value = strtolower(trim((string) $input[$key]));
            $normalized[$key] = in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        return $normalized;
    }

    private function applyPrivacyLayer1Sync(array $imprintValues, array $overrides, array $syncFlags): array
    {
        $effective = $overrides;
        foreach ($this->privacyLayer1SyncableKeys() as $key) {
            $isSynced = (bool) ($syncFlags[$key] ?? false);
            $effective[$key] = $isSynced
                ? trim((string) ($imprintValues[$key] ?? ''))
                : trim((string) ($overrides[$key] ?? ''));
        }

        return $effective;
    }

    private function privacyLayerModelForUser(int $userId, array $imprintParams = [], ?string $privacyLocale = null): array
    {
        $resolvedLocale = $this->normalizePrivacyLocale($privacyLocale);
        $defaults = $this->defaultPrivacyLayerModel($imprintParams, $resolvedLocale);
        $raw = UserData::getData($userId, $this->privacyStorageKey());
        if (!is_array($raw)) {
            return $defaults;
        }

        $imprintLayer1 = $this->privacyLayer1ImprintDefaults($imprintParams, $resolvedLocale);
        $rawLayer1 = (array) ($raw['layer1'] ?? []);
        $layer1SyncDefaults = (array) ($defaults['layer1_sync'] ?? []);
        $legacyMode = !is_array($raw['layer1_sync'] ?? null);
        $layer1Sync = $legacyMode
            ? $this->normalizePrivacyLayer1Sync([], array_fill_keys($this->privacyLayer1SyncableKeys(), false))
            : $this->normalizePrivacyLayer1Sync((array) ($raw['layer1_sync'] ?? []), $layer1SyncDefaults);

        $layer1OverridesDefaults = (array) ($defaults['layer1_overrides'] ?? []);
        $layer1Overrides = $this->normalizePrivacyLayer1(
            is_array($raw['layer1_overrides'] ?? null) ? (array) $raw['layer1_overrides'] : $rawLayer1,
            $layer1OverridesDefaults
        );
        $layer1Dpo = [
            'dpo_name' => trim((string) ($rawLayer1['dpo_name'] ?? ($defaults['layer1']['dpo_name'] ?? ''))),
            'dpo_contact' => trim((string) ($rawLayer1['dpo_contact'] ?? ($defaults['layer1']['dpo_contact'] ?? ''))),
        ];
        $layer1 = $this->applyPrivacyLayer1Sync($imprintLayer1, array_merge($layer1Overrides, $layer1Dpo), $layer1Sync);
        $layer1['dpo_name'] = $layer1Dpo['dpo_name'];
        $layer1['dpo_contact'] = $layer1Dpo['dpo_contact'];

        $savedLayer2 = (array) ($raw['layer2'] ?? []);
        $localeTextKey = 'user_privacy_text_' . $resolvedLocale;
        $layer2Text = trim((string) ($savedLayer2[$localeTextKey] ?? ''));
        if ($layer2Text === '') {
            $layer2Text = trim((string) ($savedLayer2['user_privacy_text'] ?? ''));
        }
        if ($layer2Text === '') {
            $layer2Text = $this->buildPrivacyTemplateText($layer1, $resolvedLocale);
        }
        $layer2Text = $this->stripControllerSectionFromPrivacyText($layer2Text, $resolvedLocale);
        $layer2Text = $this->stripObsoletePrivacyReferences($layer2Text, $resolvedLocale);

        $savedLayer3 = (array) ($raw['layer3'] ?? []);
        $embedMode = strtolower(trim((string) ($savedLayer3['embed_mode'] ?? 'auto')));
        if (!in_array($embedMode, ['auto', 'self'], true)) {
            $embedMode = 'auto';
        }

        return [
            'layer1' => $layer1,
            'layer1_sync' => $layer1Sync,
            'layer1_overrides' => $layer1Overrides,
            'layer1_imprint_defaults' => $imprintLayer1,
            'layer2' => [
                'user_privacy_text' => $layer2Text,
                $localeTextKey => $layer2Text,
                'template_version' => trim((string) ($savedLayer2['template_version'] ?? '2026-03')),
                'template_locale' => $resolvedLocale,
            ],
            'layer3' => [
                'embed_mode' => $embedMode,
                'self_responsibility_acknowledged' => (bool) ($savedLayer3['self_responsibility_acknowledged'] ?? false),
                'self_responsibility_acknowledged_at' => is_string($savedLayer3['self_responsibility_acknowledged_at'] ?? null)
                    ? (string) $savedLayer3['self_responsibility_acknowledged_at']
                    : null,
            ],
            'updated_at' => is_string($raw['updated_at'] ?? null) ? (string) $raw['updated_at'] : null,
        ];
    }

    private function defaultPrivacyLayerModel(array $imprintParams = [], string $privacyLocale = 'de'): array
    {
        $imprintLayer1 = $this->privacyLayer1ImprintDefaults($imprintParams, $privacyLocale);
        $layer1Sync = array_fill_keys($this->privacyLayer1SyncableKeys(), true);
        $layer1Overrides = array_fill_keys($this->privacyLayer1SyncableKeys(), '');
        $layer1 = array_merge($imprintLayer1, [
            'dpo_name' => '',
            'dpo_contact' => '',
        ]);
        $layer2Template = $this->stripControllerSectionFromPrivacyText(
            $this->buildPrivacyTemplateText($layer1, $privacyLocale),
            $privacyLocale
        );

        return [
            'layer1' => $layer1,
            'layer1_sync' => $layer1Sync,
            'layer1_overrides' => $layer1Overrides,
            'layer1_imprint_defaults' => $imprintLayer1,
            'layer2' => [
                'user_privacy_text' => $layer2Template,
                'user_privacy_text_' . $privacyLocale => $layer2Template,
                'template_version' => '2026-05',
                'template_locale' => $privacyLocale,
            ],
            'layer3' => [
                'embed_mode' => 'auto',
                'self_responsibility_acknowledged' => false,
                'self_responsibility_acknowledged_at' => null,
            ],
            'updated_at' => null,
        ];
    }

    private function normalizePrivacyLayer1(array $input, array $defaults): array
    {
        $normalized = $defaults;

        foreach (array_keys($defaults) as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $normalized[$key] = trim((string) $input[$key]);
        }

        return $normalized;
    }

    private function savePrivacyLayerModel(int $userId, array $model): void
    {
        $model['updated_at'] = now()->toIso8601String();
        UserData::saveData($userId, $this->privacyStorageKey(), $model);
    }

    private function buildPrivacyTemplateText(array $layer1, ?string $privacyLocale = null): string
    {
        $resolvedLocale = $this->normalizePrivacyLocale($privacyLocale);
        $text = $this->basePrivacyTemplateText($resolvedLocale);
        $addressLine = $this->layer1AddressLine($layer1);
        $email = trim((string) ($layer1['email'] ?? ''));
        $phone = trim((string) ($layer1['phone'] ?? ''));

        $replacements = [
            '[VORNAME NACHNAME bzw. UNTERNEHMENSNAME]' => trim((string) ($layer1['controller_name'] ?? '')) ?: '[NAME/FIRMA EINTRAGEN]',
            '[STRASSE NR, PLZ ORT]' => $addressLine !== '' ? $addressLine : '[STRASSE NR, PLZ ORT]',
            '[KONTAKT@DEINEDOMAIN.DE]' => $email !== '' ? $email : '[KONTAKT@DEINEDOMAIN.DE]',
            '[+49 XXX XXXXXXX]  (optional)' => $phone !== '' ? $phone : '[+49 XXX XXXXXXX]  (optional)',
            '[DATUM EINTRAGEN]' => now()->format('d.m.Y'),
        ];

        $text = str_replace(array_keys($replacements), array_values($replacements), $text);

        $dpoName = trim((string) ($layer1['dpo_name'] ?? ''));
        $dpoContact = trim((string) ($layer1['dpo_contact'] ?? ''));
        if ($dpoName !== '' || $dpoContact !== '') {
            $dpoLines = [];
            if ($dpoName !== '') {
                $dpoLines[] = $dpoName;
            }
            if ($dpoContact !== '') {
                $dpoLines[] = $dpoContact;
            }

            if ($resolvedLocale === 'en') {
                $insertion = "\n1a. Data Protection Officer\n" . implode("\n", $dpoLines) . "\n";
                $text = preg_replace('/\n2\.\s+General information/u', $insertion . "\n2. General information", $text, 1) ?? $text;
            } else {
                $insertion = "\n1a. Datenschutzbeauftragter\n" . implode("\n", $dpoLines) . "\n";
                $text = preg_replace('/\n2\.\s+Allgemeines/u', $insertion . "\n2. Allgemeines", $text, 1) ?? $text;
            }
        }

        return trim($text);
    }

    private function basePrivacyTemplateText(?string $privacyLocale = null): string
    {
        static $cachedByLocale = [];
        $resolvedLocale = $this->normalizePrivacyLocale($privacyLocale);
        if (isset($cachedByLocale[$resolvedLocale])) {
            return $cachedByLocale[$resolvedLocale];
        }

        $fallback = $resolvedLocale === 'en'
            ? implode("\n", [
                'Privacy Policy',
                '',
                '1. Controller',
                '[VORNAME NACHNAME bzw. UNTERNEHMENSNAME]',
                '[STRASSE NR, PLZ ORT]',
                'Email: [KONTAKT@DEINEDOMAIN.DE]',
                'Phone: [+49 XXX XXXXXXX]  (optional)',
                '',
                '2. General information',
                'We take the protection of your personal data very seriously.',
                '',
                '3. Rights of data subjects',
                'You have the rights under Art. 15 to 21 GDPR.',
                '',
                'Last updated: [DATUM EINTRAGEN]',
            ])
            : implode("\n", [
                'Datenschutzerklärung',
                '',
                '1. Verantwortlicher',
                '[VORNAME NACHNAME bzw. UNTERNEHMENSNAME]',
                '[STRASSE NR, PLZ ORT]',
                'E-Mail: [KONTAKT@DEINEDOMAIN.DE]',
                'Telefon: [+49 XXX XXXXXXX]  (optional)',
                '',
                '2. Allgemeines',
                'Wir nehmen den Schutz Ihrer personenbezogenen Daten sehr ernst.',
                '',
                '3. Betroffenenrechte',
                'Sie haben die Rechte aus Art. 15 bis 21 DSGVO.',
                '',
                'Letzte Aktualisierung: [DATUM EINTRAGEN]',
            ]);

        $path = $resolvedLocale === 'en'
            ? base_path('../legal_docs/user_datenschutz_en.txt')
            : base_path('../legal_docs/user_datenschutz.txt');
        if (!is_readable($path)) {
            $cachedByLocale[$resolvedLocale] = $fallback;
            return $cachedByLocale[$resolvedLocale];
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            $cachedByLocale[$resolvedLocale] = $fallback;
            return $cachedByLocale[$resolvedLocale];
        }

        $startAnchor = $resolvedLocale === 'en'
            ? "Privacy Policy\n\n1. Controller"
            : "Datenschutzerklärung\n\n1. Verantwortlicher";
        $startPos = strpos($raw, $startAnchor);
        if ($startPos === false) {
            $startPos = strpos($raw, $resolvedLocale === 'en' ? 'Privacy Policy' : 'Datenschutzerklärung');
        }

        $normalized = $startPos !== false ? substr($raw, (int) $startPos) : $raw;
        $splitPattern = $resolvedLocale === 'en'
            ? '/\nBlocks for third-party services(?: and forms)?/u'
            : '/\nTextbausteine für Drittdienste(?: und Formulare)?/u';
        $parts = preg_split($splitPattern, (string) $normalized, 2);
        $mainText = is_array($parts) ? trim((string) ($parts[0] ?? '')) : '';

        $cachedByLocale[$resolvedLocale] = $mainText !== '' ? $mainText : $fallback;

        return $cachedByLocale[$resolvedLocale];
    }

    private function layer1AddressLine(array $layer1): string
    {
        $street = trim((string) ($layer1['street'] ?? ''));
        $postal = trim((string) ($layer1['postal_code'] ?? ''));
        $city = trim((string) ($layer1['city'] ?? ''));

        $cityLine = trim($postal . ' ' . $city);
        $parts = array_values(array_filter([$street, $cityLine], static fn ($value): bool => $value !== ''));

        if ($parts === []) {
            return '';
        }

        $country = trim((string) ($layer1['country'] ?? ''));
        if ($country !== '') {
            $parts[] = $country;
        }

        return implode(', ', $parts);
    }

    private function renderPrivacyNoticeOutput(array $model, ?string $privacyLocale = null): string
    {
        $resolvedLocale = $this->normalizePrivacyLocale($privacyLocale);
        $layer2 = (array) ($model['layer2'] ?? []);
        $localeTextKey = 'user_privacy_text_' . $resolvedLocale;

        $baseText = trim((string) ($layer2[$localeTextKey] ?? ''));
        if ($baseText === '') {
            $baseText = trim((string) ($layer2['user_privacy_text'] ?? ''));
        }
        if ($baseText === '') {
            $baseText = $this->stripControllerSectionFromPrivacyText(
                $this->buildPrivacyTemplateText((array) ($model['layer1'] ?? []), $resolvedLocale),
                $resolvedLocale
            );
        }
        $baseText = $this->stripObsoletePrivacyReferences($baseText, $resolvedLocale);
        $baseText = $this->syncControllerSectionIntoPrivacyText($baseText, (array) ($model['layer1'] ?? []), $resolvedLocale);

        $embedMode = strtolower(trim((string) ($model['layer3']['embed_mode'] ?? 'auto')));
        $baseText = $this->applyPrivacyEmbedLayer($baseText, $resolvedLocale, $embedMode);

        return $this->appendWayvioTemplateFooter($baseText, $resolvedLocale);
    }

    private function privacyCentralEmbedSectionText(?string $privacyLocale = null): string
    {
        static $cachedByLocale = [];
        $resolvedLocale = $this->normalizePrivacyLocale($privacyLocale);
        if (isset($cachedByLocale[$resolvedLocale])) {
            return $cachedByLocale[$resolvedLocale];
        }

        $path = $resolvedLocale === 'en'
            ? base_path('../legal_docs/user_datenschutz_embed_abschnitt_en.txt')
            : base_path('../legal_docs/user_datenschutz_embed_abschnitt.txt');
        if (is_readable($path)) {
            $raw = file_get_contents($path);
            if (is_string($raw) && trim($raw) !== '') {
                $cachedByLocale[$resolvedLocale] = $this->stripEmbedSectionMetaLines(trim($raw));
                return $cachedByLocale[$resolvedLocale];
            }
        }

        $cachedByLocale[$resolvedLocale] = $resolvedLocale === 'en'
            ? implode("\n", [
                '5. Embedded third-party services (central embed section)',
                'Wayvio supports embeds for YouTube, Instagram, Google Maps, Spotify, Calendly, Tally, Gumroad, Kit and Resmio.',
                'External content is loaded only after active user consent via opt-in banner.',
                'Without consent, external content remains blocked.',
                'After consent, providers may process personal data such as IP address, browser details and referrer data.',
            ])
            : implode("\n", [
                '5. Eingebundene Drittdienste (zentraler Embed-Abschnitt)',
                'Wayvio unterstützt externe Einbindungen für YouTube, Instagram, Google Maps, Spotify, Calendly, Tally, Gumroad, Kit und Resmio.',
                'Externe Inhalte werden erst nach aktiver Einwilligung durch den Besucher geladen (Opt-in-Banner).',
                'Ohne Einwilligung bleibt der Inhalt blockiert und wird nicht geladen.',
                'Bei der Freigabe können je nach Dienst personenbezogene Daten (z. B. IP-Adresse, Browserdaten, Referrer) an den jeweiligen Anbieter übertragen werden.',
            ]);

        $cachedByLocale[$resolvedLocale] = $this->stripEmbedSectionMetaLines($cachedByLocale[$resolvedLocale]);

        return $cachedByLocale[$resolvedLocale];
    }

    private function privacyManualEmbedSectionText(string $locale): string
    {
        if ($locale === 'en') {
            return '5. Embedded services and forms';
        }

        return '5. Embed Dienste und Formulare';
    }

    private function applyPrivacyEmbedLayer(string $baseText, string $locale, string $embedMode): string
    {
        $normalizedMode = $embedMode === 'auto' ? 'auto' : 'self';
        $normalizedText = trim($baseText);
        if ($normalizedText === '') {
            return '';
        }

        $marker = '{{LAYER3_EMBEDS}}';
        $replacement = $normalizedMode === 'auto'
            ? trim($this->privacyCentralEmbedSectionText($locale))
            : trim($this->privacyManualEmbedSectionText($locale));

        if (str_contains($normalizedText, $marker)) {
            $updated = str_replace($marker, "\n" . $replacement . "\n", $normalizedText);
            return $this->normalizePrivacyText($updated);
        }

        $legacyReplaced = $this->replaceLegacyEmbedSection($normalizedText, $replacement, $locale);
        if ($legacyReplaced !== null) {
            return $legacyReplaced;
        }

        if ($replacement !== '') {
            $nextSectionNumber = $this->nextMainSectionNumber($normalizedText);
            $embedSection = $this->renumberEmbedSection($replacement, $nextSectionNumber);
            return $this->normalizePrivacyText(trim($normalizedText) . "\n\n" . $embedSection);
        }

        return $this->normalizePrivacyText($normalizedText);
    }

    private function syncControllerSectionIntoPrivacyText(string $text, array $layer1, string $locale): string
    {
        $normalizedText = trim($text);
        $controllerSection = $this->privacyControllerSectionText($layer1, $locale);
        $replacement = $controllerSection . "\n\n";
        $pattern = $this->privacyControllerSectionPattern($locale);

        $updated = preg_replace($pattern, $replacement, $normalizedText, 1, $count);
        if ($count >= 1 && is_string($updated)) {
            return $this->normalizePrivacyText($updated);
        }

        $title = $locale === 'en' ? 'Privacy Policy' : 'Datenschutzerklärung';
        $titlePattern = '/^\s*' . preg_quote($title, '/') . '\h*$/mu';
        $inserted = preg_replace($titlePattern, $title . "\n\n" . $controllerSection, $normalizedText, 1, $titleCount);
        if ($titleCount >= 1 && is_string($inserted)) {
            return $this->normalizePrivacyText($inserted);
        }

        if ($normalizedText === '') {
            return $this->normalizePrivacyText($controllerSection);
        }

        return $this->normalizePrivacyText($controllerSection . "\n\n" . $normalizedText);
    }

    private function privacyControllerSectionPattern(string $locale): string
    {
        return $locale === 'en'
            ? '/^\s*1\.\s+Controller\b[\s\S]*?(?=^\s*\d+\.\s+|\z)/mu'
            : '/^\s*1\.\s+Verantwortlicher\b[\s\S]*?(?=^\s*\d+\.\s+|\z)/mu';
    }

    private function stripControllerSectionFromPrivacyText(string $text, string $locale): string
    {
        $normalizedText = trim($text);
        if ($normalizedText === '') {
            return '';
        }

        $updated = preg_replace($this->privacyControllerSectionPattern($locale), '', $normalizedText, 1);
        if (!is_string($updated)) {
            return $this->normalizePrivacyText($normalizedText);
        }

        return $this->normalizePrivacyText($updated);
    }

    private function privacyControllerSectionText(array $layer1, string $locale): string
    {
        $controllerName = trim((string) ($layer1['controller_name'] ?? ''));
        $addressLine = $this->layer1AddressLine($layer1);
        $email = trim((string) ($layer1['email'] ?? ''));
        $phone = trim((string) ($layer1['phone'] ?? ''));

        if ($locale === 'en') {
            $lines = [
                '1. Controller',
                $controllerName !== '' ? $controllerName : '[NAME/COMPANY]',
                $addressLine !== '' ? $addressLine : '[STREET, POSTAL CODE, CITY]',
                'Email: ' . ($email !== '' ? $email : '[KONTAKT@DEINEDOMAIN.DE]'),
            ];
            if ($phone !== '') {
                $lines[] = 'Phone: ' . $phone;
            }

            return trim(implode("\n", $lines));
        }

        $lines = [
            '1. Verantwortlicher',
            $controllerName !== '' ? $controllerName : '[NAME/FIRMA EINTRAGEN]',
            $addressLine !== '' ? $addressLine : '[STRASSE NR, PLZ ORT]',
            'E-Mail: ' . ($email !== '' ? $email : '[KONTAKT@DEINEDOMAIN.DE]'),
        ];
        if ($phone !== '') {
            $lines[] = 'Telefon: ' . $phone;
        }

        return trim(implode("\n", $lines));
    }

    private function replaceLegacyEmbedSection(string $text, string $replacement, string $locale): ?string
    {
        $pattern = $locale === 'en'
            ? '/^\s*5\.\s+(?:Embedded third-party services|Embedded services|Embedded services and forms)\b[\s\S]*?(?=^\s*\d+\.\s+|\z)/mu'
            : '/^\s*5\.\s+(?:Eingebundene Drittdienste|Embed Dienste|Embed Dienste und Formulare)\b[\s\S]*?(?=^\s*\d+\.\s+|\z)/mu';

        $updated = preg_replace($pattern, trim($replacement) . "\n\n", $text, 1, $count);
        if ($count < 1 || !is_string($updated)) {
            return null;
        }

        return $this->normalizePrivacyText($updated);
    }

    private function stripObsoletePrivacyReferences(string $text, ?string $locale = null): string
    {
        $updated = preg_replace('/^\h*Die entsprechenden Textbausteine finden sich ab Seite 2 dieses Dokuments\.\h*$/mu', '', $text) ?? $text;
        $updated = preg_replace('/^\h*The corresponding text modules can be found on page 2 of this document\.\h*$/mu', '', $updated) ?? $updated;
        return $this->normalizePrivacyText($updated);
    }

    private function appendWayvioTemplateFooter(string $text, string $locale): string
    {
        $cleanText = $this->stripWayvioTemplateFooter($text);
        $footerBlock = $this->wayvioTemplateFooterBlock($locale);
        if ($cleanText === '') {
            return $footerBlock;
        }

        return $this->normalizePrivacyText($cleanText . "\n\n" . $footerBlock);
    }

    private function stripWayvioTemplateFooter(string $text): string
    {
        $updated = preg_replace('/^\h*Vorlage bereitgestellt durch Wayvio.*$/mu', '', $text) ?? $text;
        $updated = preg_replace('/^\h*Template provided by Wayvio.*$/mu', '', $updated) ?? $updated;
        $updated = preg_replace('/^\h*Wayvio-Disclaimer:.*$/mu', '', $updated) ?? $updated;
        return $this->normalizePrivacyText($updated);
    }

    private function wayvioTemplateFooterBlock(string $locale): string
    {
        if ($locale === 'en') {
            return implode("\n", [
                'Template provided by Wayvio | Version: May 2026',
                'Wayvio-Disclaimer: This template is provided as non-binding guidance only and does not constitute legal advice. Wayvio assumes no liability and provides no warranty for accuracy, completeness or currency. Please obtain legal review before publishing.',
            ]);
        }

        return implode("\n", [
            'Vorlage bereitgestellt durch Wayvio | Stand: Mai 2026',
            'Wayvio-Disclaimer: Diese Vorlage dient ausschließlich als unverbindliche Orientierung und stellt keine Rechtsberatung dar. Wayvio übernimmt keine Haftung und keine Gewähr für Richtigkeit, Vollständigkeit oder Aktualität. Bitte lassen Sie die finale Datenschutzerklärung rechtlich prüfen.',
        ]);
    }

    private function stripEmbedSectionMetaLines(string $text): string
    {
        $updated = preg_replace('/^\h*(Stand|Date)\s*:\s*.*$/mu', '', $text) ?? $text;
        return $this->normalizePrivacyText($updated);
    }

    private function normalizePrivacyText(string $text): string
    {
        $updated = str_replace(["\r\n", "\r"], "\n", $text);
        $updated = preg_replace('/(?<!\n)\n(?=\d+\.\s+)/u', "\n\n", $updated) ?? $updated;
        $updated = preg_replace("/\n{3,}/", "\n\n", $updated) ?? $updated;
        return trim($updated);
    }

    private function nextMainSectionNumber(string $text): int
    {
        $matchCount = preg_match_all('/^\s*(\d+)\.\s+/m', $text, $matches);
        if ($matchCount === false || $matchCount === 0) {
            return 1;
        }

        $numbers = array_map('intval', (array) ($matches[1] ?? []));
        if ($numbers === []) {
            return 1;
        }

        return max($numbers) + 1;
    }

    private function renumberEmbedSection(string $embedSection, int $sectionNumber): string
    {
        $normalizedSectionNumber = max(1, (int) $sectionNumber);
        $replacementPrefix = $normalizedSectionNumber . '. ';

        $updated = preg_replace('/^\s*\d+\.\s+/m', $replacementPrefix, $embedSection, 1);
        if (!is_string($updated) || trim($updated) === '') {
            return $replacementPrefix . trim($embedSection);
        }

        return trim($updated);
    }

    private function normalizePrivacyLocale(?string $locale): string
    {
        $candidate = strtolower(trim((string) $locale));
        if ($candidate === '') {
            $candidate = strtolower(trim((string) app()->getLocale()));
        }

        $base = strtok(str_replace('_', '-', $candidate), '-');
        return $base === 'en' ? 'en' : 'de';
    }

    private function privacyLocaleForUserId(int $userId): string
    {
        if ($userId <= 0) {
            return $this->normalizePrivacyLocale((string) app()->getLocale());
        }

        $query = User::query()->select('id');
        if (Schema::hasColumn('users', 'locale')) {
            $query->addSelect('locale');
        }
        if (Schema::hasColumn('users', 'last_login_locale')) {
            $query->addSelect('last_login_locale');
        }

        $user = $query->find($userId);
        if (!$user) {
            return $this->normalizePrivacyLocale((string) app()->getLocale());
        }

        return $this->normalizePrivacyLocale($this->resolvePublicPageLocale($user));
    }

    private function resolvePublicPageLocale(User $pageUser): string
    {
        $supported = array_values(array_filter(
            (array) config('app.supported_locales', []),
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));

        $map = [];
        foreach ($supported as $locale) {
            $normalized = strtolower(trim((string) $locale));
            if ($normalized !== '') {
                $map[$normalized] = trim((string) $locale);
            }
        }

        $normalize = static function (?string $locale) use ($map): ?string {
            $candidate = strtolower(trim((string) $locale));
            if ($candidate === '') {
                return null;
            }

            $candidate = str_replace('_', '-', $candidate);
            $resolved = null;

            if (isset($map[$candidate])) {
                $resolved = $map[$candidate];
            }

            if ($resolved === null) {
                $base = strtok($candidate, '-');
                if (is_string($base) && $base !== '' && isset($map[$base])) {
                    $resolved = $map[$base];
                }
            }

            if ($resolved === null) {
                $direct = trim(str_replace('_', '-', (string) $locale));
                if ($direct !== '' && is_dir(lang_path($direct))) {
                    $resolved = $direct;
                }
            }

            if ($resolved === null) {
                return null;
            }

            $resolved = trim((string) $resolved);
            if ($resolved === '') {
                return null;
            }

            if (is_dir(lang_path($resolved))) {
                return $resolved;
            }

            $lower = strtolower($resolved);
            if (is_dir(lang_path($lower))) {
                return $lower;
            }

            return $resolved;
        };

        $resolved = $normalize(is_string($pageUser->locale ?? null) ? (string) $pageUser->locale : null);
        if ($resolved !== null) {
            return $resolved;
        }

        $owner = app(DomainUrlResolver::class)->ownerForPageUser($pageUser);
        if ($owner && (int) $owner->id !== (int) $pageUser->id) {
            $resolved = $normalize(is_string($owner->locale ?? null) ? (string) $owner->locale : null);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $resolved = $normalize((string) app()->getLocale());
        if ($resolved !== null) {
            return $resolved;
        }

        $resolved = $normalize((string) config('app.fallback_locale', 'en'));
        if ($resolved !== null) {
            return $resolved;
        }

        return $map['de'] ?? (array_values($map)[0] ?? 'de');
    }

    /**
     * @return array{max_kb:int,max_width:int,max_height:int,max_mb:int}
     */
    private function imageUploadLimit(string $limitKey, int $defaultKb, int $defaultWidth, int $defaultHeight): array
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

    private function ownerCanAccessFeature(string $featureKey, ?Request $request = null): bool
    {
        $owner = $request?->user() ?? Auth::user();
        if (!$owner) {
            return false;
        }

        if (in_array((string) ($owner->role ?? ''), ['vip', 'admin'], true)) {
            return true;
        }

        if (!class_exists(SubscriptionManager::class) || !class_exists(TierResolver::class)) {
            return false;
        }

        if (!Schema::hasTable('tiers') || !Schema::hasTable('user_subscriptions')) {
            return false;
        }

        $subscriptionManager = app(SubscriptionManager::class);
        $tierResolver = app(TierResolver::class);
        $tier = $subscriptionManager->getUserTier($owner);

        return $tierResolver->featureEnabled($tier, $featureKey);
    }

    private function requiredTierLabelForFeature(string $featureKey, string $fallback = 'Basic'): string
    {
        if (!class_exists(TierResolver::class)) {
            return $fallback;
        }

        $tierResolver = app(TierResolver::class);
        $tierOrder = config('tiers.order', ['free', 'basic', 'pro', 'agency']);

        foreach ($tierOrder as $slug) {
            $plan = $tierResolver->configForSlug((string) $slug);
            if ((bool) data_get($plan, 'features.' . $featureKey, false)) {
                return (string) ($plan['name'] ?? ucfirst((string) $slug));
            }
        }

        return $fallback;
    }

    private function ensureOwnerFeatureAccess(Request $request, string $featureKey, string $message): void
    {
        if (!$this->ownerCanAccessFeature($featureKey, $request)) {
            abort(403, $message);
        }
    }

    private function tenantOwnerIdForEditorUser(int $resourceUserId): ?int
    {
        $contextTenantOwnerId = function_exists('currentTenantOwnerId') ? currentTenantOwnerId() : null;
        if (is_int($contextTenantOwnerId) && $contextTenantOwnerId > 0) {
            return $contextTenantOwnerId;
        }

        if ($resourceUserId <= 0) {
            return null;
        }

        try {
            $resolved = app(TenantResolver::class)->ownerIdForUserId($resourceUserId);
            return is_int($resolved) && $resolved > 0 ? $resolved : $resourceUserId;
        } catch (\Throwable) {
            return $resourceUserId;
        }
    }

    private function resolvePersistableButtonId(mixed $candidate): ?int
    {
        $candidateId = (int) $candidate;
        if ($candidateId > 0 && Button::query()->whereKey($candidateId)->exists()) {
            return $candidateId;
        }

        $legacyButtonNames = [
            1 => 'custom',
            2 => 'custom_website',
            6 => 'default email',
            42 => 'heading',
            43 => 'space',
            44 => 'phone',
            93 => 'text',
            96 => 'vcard',
        ];

        $legacyName = $legacyButtonNames[$candidateId] ?? null;
        if ($legacyName !== null) {
            $resolvedId = Button::query()->where('name', $legacyName)->value('id');
            if ($resolvedId !== null) {
                return (int) $resolvedId;
            }
        }

        $fallbackId = Button::query()->where('name', 'custom')->value('id');
        return $fallbackId !== null ? (int) $fallbackId : null;
    }

    private function activeEditorUserId(?Request $request = null): int
    {
        $owner = Auth::user();
        if (!$owner) {
            return 0;
        }

        $activeUserId = app(AgencyHubContext::class)->editingUserId($owner, $request);
        if ($activeUserId > 0) {
            Gate::forUser($owner)->authorize('tenant.access-resource', $activeUserId);
        }

        return $activeUserId;
    }

    private function denyTenantPageContextMismatch(
        Request $request,
        int $activeResourceUserId,
        ?int $suppliedPageId,
        string $reasonCode,
        ?string $message = null,
    ): void {
        if ($suppliedPageId === null || $suppliedPageId === $activeResourceUserId) {
            return;
        }

        $tenantOwnerUserId = $this->tenantOwnerIdForEditorUser($activeResourceUserId);
        \Log::warning('Tenant page context denied', [
            'reason_code' => $reasonCode,
            'actor_user_id' => (int) (Auth::id() ?? 0),
            'resource_user_id' => $suppliedPageId,
            'active_resource_user_id' => $activeResourceUserId,
            'tenant_owner_user_id' => $tenantOwnerUserId,
            'route' => (string) ($request->route()?->getName() ?: $request->path()),
        ]);

        abort(403, $message ?? 'Forbidden');
    }

    /**
     * Resolve block-specific handler function (preferred) with legacy fallback.
     */
    private function resolveBlockHandlerFunction(string $typeName): string
    {
        $normalizedType = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $typeName), '_'));
        if ($normalizedType !== '') {
            $specificFunction = 'handleLinkType_' . $normalizedType;
            if (function_exists($specificFunction)) {
                return $specificFunction;
            }
        }

        return 'handleLinkType';
    }

    /**
     * @return array<string,mixed>
     */
    private function executeBlockHandler(Request $request, LinkType $linkType): array
    {
        $handlerFunction = $this->resolveBlockHandlerFunction((string) ($linkType->typename ?? ''));
        if (!function_exists($handlerFunction)) {
            abort(500, 'Link type handler function not found.');
        }

        $result = $handlerFunction($request, $linkType);
        return is_array($result) ? $result : [];
    }
}
