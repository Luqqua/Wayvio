<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

use GeoSot\EnvEditor\Controllers\EnvController;
use GeoSot\EnvEditor\Exceptions\EnvException;
use GeoSot\EnvEditor\Helpers\EnvFileContentManager;
use GeoSot\EnvEditor\Helpers\EnvFilesManager;
use GeoSot\EnvEditor\Helpers\EnvKeysManager;
use GeoSot\EnvEditor\Facades\EnvEditor;
use GeoSot\EnvEditor\ServiceProvider;

use Auth;
use Exception;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

use App\Models\User;
use App\Models\Admin;
use App\Models\Button;
use App\Models\Link;
use App\Models\Page;
use App\Models\UserData;
use App\Services\AccountDeletionService;
use App\Services\Exceptions\AccountDeletionBlockedException;
use App\Services\Agency\AgencyHubContext;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\Compliance\LegalAgreementTextService;
use Modules\Tiers\Services\SubscriptionManager; /* MODULE: SaaS */
use Modules\Tiers\Models\Tier; /* MODULE: SaaS */
use Modules\Tiers\Models\UserSubscription; /* MODULE: SaaS */

class AdminController extends Controller
{
    //General site statistics
    public function index()
    {
        $currentUser = Auth::user();
        $agencySummary = null;
        $isAgencyTier = false;

        $userNumber = User::query()->withoutAgencyHubAccounts()->count();
        $siteLinks = Link::count();

        $users = User::query()
            ->withoutAgencyHubAccounts()
            ->select('id', 'created_at', 'updated_at')
            ->get();
        $lastMonthCount = $users->where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $lastWeekCount = $users->where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $last24HrsCount = $users->where('created_at', '>=', Carbon::now()->subHours(24))->count();
        $updatedLast30DaysCount = $users->where('updated_at', '>=', Carbon::now()->subDays(30))->count();
        $updatedLast7DaysCount = $users->where('updated_at', '>=', Carbon::now()->subDays(7))->count();
        $updatedLast24HrsCount = $users->where('updated_at', '>=', Carbon::now()->subHours(24))->count();

        if ($currentUser) {
            $currentTier = app(SubscriptionManager::class)->getUserTier($currentUser);
            $isAgencyTier = ($currentTier?->slug ?? '') === 'agency';

            $agencyContext = app(AgencyHubContext::class);
            if ($agencyContext->isAgencyAccount($currentUser)) {
                $hubRows = $agencyContext->hubs($currentUser);
                $hubUserIds = $hubRows->pluck('managed_user_id')->map(fn ($id) => (int) $id)->all();
                $slotSummary = $agencyContext->slotSummary($currentUser);

                $agencySummary = [
                    'hub_count' => count($hubUserIds),
                    'slots_used' => (int) ($slotSummary['used'] ?? 0),
                    'slots_total' => (int) ($slotSummary['total'] ?? 0),
                    'links' => $hubUserIds ? Link::withDisabled()->whereIn('user_id', $hubUserIds)->count() : 0,
                ];
            }
        }

        return view('panel/index', [
            'lastMonthCount' => $lastMonthCount,
            'lastWeekCount' => $lastWeekCount,
            'last24HrsCount' => $last24HrsCount,
            'updatedLast30DaysCount' => $updatedLast30DaysCount,
            'updatedLast7DaysCount' => $updatedLast7DaysCount,
            'updatedLast24HrsCount' => $updatedLast24HrsCount,
            'siteLinks' => $siteLinks,
            'userNumber' => $userNumber,
            'agencySummary' => $agencySummary,
            'isAgencyTier' => $isAgencyTier,
        ]);
    }

// Users page
public function users()
{
    return view('panel/users');
}

// Send test mail
public function SendTestMail(Request $request)
{
    try {
        $userId = auth()->id();
        $user = User::findOrFail($userId);
        
        Mail::send('auth.test', ['user' => $user], function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Test Email');
        });
        
        return redirect()->route('showConfig')->with('success', 'Test email sent successfully!');
    } catch (\Exception $e) {
        return redirect()->route('showConfig')->with('fail', 'Failed to send test email.');
    }
}

    //Block user
    public function blockUser(request $request)
    {
        $id = $request->id;
        $status = $request->block;

        if ($status == 'yes') {
            $block = 'no';
        } elseif ($status == 'no') {
            $block = 'yes';
        }

        User::where('id', $id)->update(['block' => $block]);

        return redirect('admin/users/all');
    }

    //Verify user
    public function verifyCheckUser(request $request)
    {
        $id = $request->id;
        $status = $request->verify;

        if ($status == 'vip') {
            $verify = 'vip';
            UserData::saveData($id, 'checkmark', true);
        } elseif ($status == 'user') {
            $verify = 'user';
        }

        User::where('id', $id)->update(['role' => $verify]);

        return redirect(url('u')."/".$id);
    }

    //Verify or un-verify users emails
    public function verifyUser(request $request)
    {
        $id = $request->id;
        $status = $request->verify;

        if ($status == "true") {
            $verify = '0000-00-00 00:00:00';
        } else {
            $verify = NULL;
        }

        User::where('id', $id)->update(['email_verified_at' => $verify]);
    }

    //Create new user from the Admin Panel
    public function createNewUser()
    {

        function random_str(
            int $length = 64,
            string $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
        ): string {
            if ($length < 1) {
                throw new \RangeException("Length must be a positive integer");
            }
            $pieces = [];
            $max = mb_strlen($keyspace, '8bit') - 1;
            for ($i = 0; $i < $length; ++$i) {
                $pieces[] = $keyspace[random_int(0, $max)];
            }
            return implode('', $pieces);
        }

        $names = User::pluck('name')->toArray();

        $adminCreatedNames = array_filter($names, function($name) {
            return strpos($name, 'Admin-Created-') === 0;
        });

        $numbers = array_map(function($name) {
            return (int) str_replace('Admin-Created-', '', $name);
        }, $adminCreatedNames);

        $maxNumber = !empty($numbers) ? max($numbers) : 0;
        $newNumber = $maxNumber + 1;

        $domain = parse_url(url(''), PHP_URL_HOST);
        $domain = ($domain == 'localhost') ? 'example.com' : $domain;

        $user = User::create([
            'name' => 'Admin-Created-' . $newNumber,
            'email' => strtolower(random_str(8)) . '@' . $domain,
            'password' => Hash::make(random_str(32)),
            'role' => User::ROLE_USER,
            'block' => 'no',
        ]);

        applyWayvioDefaultDesignSettings((int) $user->id);

        return redirect('admin/edit-user/' . $user->id);
    }

    //Delete existing user
    public function deleteUser(request $request, AccountDeletionService $accountDeletionService)
    {
        $id = (int) $request->id;
        $user = User::find($id);
        if (! $user) {
            return redirect('admin/users/all');
        }

        try {
            $accountDeletionService->deleteUser($user, [
                'source' => 'admin_panel',
                'actor_user_id' => (int) ($request->user()?->id ?? 0),
                'deletion_reason' => 'admin',
            ]);
        } catch (AccountDeletionBlockedException $e) {
            report($e);

            return redirect('admin/users/all')->withErrors([
                'delete_user' => 'User could not be deleted because subscription cancellation failed.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect('admin/users/all')->withErrors([
                'delete_user' => 'User could not be deleted right now.',
            ]);
        }

        return redirect('admin/users/all');
    }

    //Delete existing user with POST request
    public function deleteTableUser(request $request, AccountDeletionService $accountDeletionService)
    {
        $id = (int) $request->id;
        $user = User::find($id);
        if (! $user) {
            return response()->noContent();
        }

        try {
            $accountDeletionService->deleteUser($user, [
                'source' => 'admin_panel',
                'actor_user_id' => (int) ($request->user()?->id ?? 0),
                'deletion_reason' => 'admin',
            ]);
        } catch (AccountDeletionBlockedException $e) {
            report($e);

            return response()->json([
                'error' => 'subscription_cancellation_failed',
            ], 409);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'user_delete_failed',
            ], 500);
        }

        return response()->noContent();
    }

    //Show user to edit
    public function showUser(request $request)
    {
        $id = $request->id;

        $data['user'] = User::where('id', $id)->get();
        /* MODULE: SaaS tiers injected into core view */
        $data['tiers'] = Tier::all();
        $currentTier = app(SubscriptionManager::class)->getUserTier(User::find($id));
        $data['currentTierSlug'] = $currentTier?->slug ?? 'free';

        return view('panel/edit-user', $data);
    }

    //Show user links in links page
    public function showLinksUser(request $request)
    {
        $id = $request->id;

        $data['user'] = User::where('id', $id)->get();

        $data['links'] = Link::select('id', 'link', 'title', 'order', 'up_link', 'links.button_id')->where('user_id', $id)->orderBy('up_link', 'asc')->orderBy('order', 'asc')->paginate(10);
        return view('panel/links', $data);
    }

    //Delete link
    public function deleteLinkUser(request $request)
    {
        $linkId = $request->id;

        Link::where('id', $linkId)->delete();

        return back();
    }

    //Save user edit
    public function editUser(request $request)
    {
        if ($request->hasFile('image') || $request->hasFile('background')) {
            abort(403, 'Admin-side image uploads are disabled. Use the user workspace or SSH/CLI workflows instead.');
        }

        $request->validate([
            'name' => '',
            'email' => '',
            'password' => '',
            'littlelink_name' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[\p{L}0-9-_]+$/u',
                Rule::notIn(reservedSlugs()),
                'isunique:users,id,'.$request->id,
            ],
        ]);

        $id = $request->id;
        $name = $request->name;
        $email = $request->email;
        $password = Hash::make($request->password);
        $littlelink_name = $request->littlelink_name;
        $littlelink_description = trim(strip_tags((string) $request->littlelink_description));
        $role = $request->role;
        $tierId = $request->input('tier_id');
        $theme = $request->theme;

        if(User::where('id', $id)->get('role')->first()->role =! $role) {
            if ($role == 'vip') {
                UserData::saveData($id, 'checkmark', true);
            }
        }

        $userModel = User::find($id);

        if ($request->password == '') {
            $userModel->update(['name' => $name, 'email' => $email, 'littlelink_name' => $littlelink_name, 'littlelink_description' => $littlelink_description, 'role' => $role, 'theme' => $theme]);
        } else {
            $userModel->update(['name' => $name, 'email' => $email, 'password' => $password, 'littlelink_name' => $littlelink_name, 'littlelink_description' => $littlelink_description, 'role' => $role, 'theme' => $theme]);
        }

        /* MODULE: SaaS tier assignment */
        if ($role === 'admin') {
            $adminSlug = config('tiers.admin_tier_slug', 'business');
            $targetTier = \Modules\Tiers\Models\Tier::where('slug', $adminSlug)->first();
        } elseif ($tierId) {
            $targetTier = \Modules\Tiers\Models\Tier::find($tierId);
        } else {
            $targetTier = \Modules\Tiers\Models\Tier::where('slug', config('tiers.default_free_slug', 'free'))->first();
        }
        if ($targetTier) {
            app(\Modules\Tiers\Services\SubscriptionManager::class)->assignTier($userModel, $targetTier, null);
        } else {
            \Modules\Tiers\Models\UserSubscription::where('user_id', $userModel->id)->delete();
        }
        return redirect('admin/users/all');
    }

    //Show site pages to edit
    public function showSitePage()
    {
        $data['pages'] = Page::select('terms', 'privacy', 'contact', 'register')->get();
        return view('panel/pages', $data);
    }

    //Save site pages
    public function editSitePage(request $request)
    {
        $terms = $request->terms;
        $privacy = $request->privacy;
        $contact = $request->contact;
        $register = $request->register;

        Page::first()->update(['terms' => $terms, 'privacy' => $privacy, 'contact' => $contact, 'register' => $register]);

        return back();
    }

    //Show home message for edit
    public function showSite()
    {
        $message = Page::select('home_message')->first();
        return view('panel/site', $message);
    }

    //Save home message, logo and favicon
    public function editSite(request $request)
    {
        if ($request->hasFile('image') || $request->hasFile('icon')) {
            abort(403, 'Site logo and favicon updates are CLI-only. Use SSH or the branding:set-site-asset command.');
        }

        $message = $request->message;

        Page::first()->update(['home_message' => $message]);
        return back();
    }

    //Delete avatar
    public function delAvatar()
    {
        abort(403, 'Site logo deletion is CLI-only. Use SSH or the branding:set-site-asset command.');
    }

    //Delete favicon
    public function delFavicon()
    {
        abort(403, 'Favicon deletion is CLI-only. Use SSH or the branding:set-site-asset command.');
    }

    //View footer page: terms
    public function pagesTerms(Request $request)
    {
        $name = "terms";
    
        try {
            $data['page'] = Page::select($name)->first();
        } catch (Exception $e) {
            return abort(404);
        }
    
        return view('pages', ['data' => $data, 'name' => $name]);
    }

    public function pagesAgb(Request $request)
    {
        $legalLocale = $this->resolveLegalDocumentLocale($request);
        app()->setLocale($legalLocale);

        $snapshot = app(ComplianceAuditService::class)->currentAgreementSnapshot('agb');
        $document = app(LegalAgreementTextService::class)->loadAgreementText('agb', $legalLocale);
        if (!is_array($document)) {
            abort(503, 'AGB document is currently unavailable.');
        }

        return view('pages.legal-agreement', array_merge([
            'agreementType' => 'agb',
            'agreementLabel' => $legalLocale === 'en'
                ? 'General Terms and Conditions (AGB)'
                : 'Allgemeine Geschaeftsbedingungen (AGB)',
            'agreementVersion' => $snapshot['version'],
            'agreementUrl' => route('pagesAgb', ['legal_lang' => $legalLocale], false),
            'documentText' => $document['text'],
            'documentHash' => $document['sha256'],
            'sections' => [],
        ], $this->legalPageViewData($request, $legalLocale)));
    }

    public function pagesAvv(Request $request)
    {
        $legalLocale = $this->resolveLegalDocumentLocale($request);
        app()->setLocale($legalLocale);

        $snapshot = app(ComplianceAuditService::class)->currentAgreementSnapshot('avv');
        $document = app(LegalAgreementTextService::class)->loadAgreementText('avv', $legalLocale);
        if (!is_array($document)) {
            abort(503, 'AVV document is currently unavailable.');
        }

        return view('pages.legal-agreement', array_merge([
            'agreementType' => 'avv',
            'agreementLabel' => $legalLocale === 'en'
                ? 'Data Processing Agreement (DPA / AVV)'
                : 'Auftragsverarbeitungsvertrag (AVV)',
            'agreementVersion' => $snapshot['version'],
            'agreementUrl' => route('pagesAvv', ['legal_lang' => $legalLocale], false),
            'documentText' => $document['text'],
            'documentHash' => $document['sha256'],
            'sections' => [],
        ], $this->legalPageViewData($request, $legalLocale)));
    }

    //View footer page: privacy
    public function pagesPrivacy(Request $request)
    {
        $legalLocale = $this->resolveLegalDocumentLocale($request);
        app()->setLocale($legalLocale);

        $document = app(LegalAgreementTextService::class)->loadAgreementText('privacy', $legalLocale);
        if (!is_array($document)) {
            abort(503, 'Privacy document is currently unavailable.');
        }

        $config = (array) config('legal.documents.privacy', []);
        $label = trim((string) ($config['label'] ?? 'Datenschutzerklaerung'));
        $version = trim((string) ($config['version'] ?? 'unknown'));
        if ($label === '') {
            $label = 'Datenschutzerklaerung';
        }
        if ($version === '') {
            $version = 'unknown';
        }
        if ($legalLocale === 'en') {
            $label = 'Privacy Policy';
        }

        return view('pages.legal-agreement', array_merge([
            'agreementType' => 'privacy',
            'agreementLabel' => $label,
            'agreementVersion' => $version,
            'agreementUrl' => route('pagesPrivacy', ['legal_lang' => $legalLocale], false),
            'documentText' => $document['text'],
            'documentHash' => $document['sha256'],
            'sections' => [],
        ], $this->legalPageViewData($request, $legalLocale)));
    }

    public function pagesImprint(Request $request)
    {
        $legalLocale = $this->resolveLegalDocumentLocale($request);
        app()->setLocale($legalLocale);

        $document = app(LegalAgreementTextService::class)->loadAgreementText('imprint', $legalLocale);
        if (!is_array($document)) {
            abort(503, 'Imprint document is currently unavailable.');
        }

        $config = (array) config('legal.documents.imprint', []);
        $label = trim((string) ($config['label'] ?? 'Impressum'));
        $version = trim((string) ($config['version'] ?? '2026-03'));
        if ($label === '') {
            $label = 'Impressum';
        }
        if ($version === '') {
            $version = '2026-03';
        }
        if ($legalLocale === 'en') {
            $label = 'Imprint';
        }

        $platformFormsHub = null;
        try {
            $platformHubId = (int) config('forms.platform_hub_user_id', 0);
            $platformFormsHub = $platformHubId > 0
                ? \App\Models\User::query()->find($platformHubId)
                : \App\Models\User::query()->whereNotNull('email')->orderBy('id')->first();
        } catch (\Throwable) {
            $platformFormsHub = null;
        }

        return view('pages.legal-agreement', array_merge([
            'agreementType' => 'imprint',
            'agreementLabel' => $label,
            'agreementVersion' => $version,
            'agreementUrl' => route('pagesImprint', ['legal_lang' => $legalLocale], false),
            'documentText' => $document['text'],
            'documentHash' => $document['sha256'],
            'sections' => [],
            'platformFormsHub' => $platformFormsHub,
        ], $this->legalPageViewData($request, $legalLocale)));
    }

    private function resolveLegalDocumentLocale(Request $request): string
    {
        $candidate = strtolower(trim((string) $request->query('legal_lang', '')));
        $candidate = str_replace('_', '-', $candidate);
        $base = strtok($candidate, '-');
        if (is_string($base) && in_array($base, ['de', 'en'], true)) {
            return $base;
        }

        return 'de';
    }

    /**
     * @return array{legalLocale:string,legalLinks:array{agb:string,avv:string,privacy:string,imprint:string},legalLocaleSwitchUrls:array{de:string,en:string}}
     */
    private function legalPageViewData(Request $request, string $legalLocale): array
    {
        $currentPath = '/' . ltrim((string) $request->path(), '/');
        $currentPath = $currentPath === '/' ? $request->url() : $currentPath;

        return [
            'legalLocale' => $legalLocale,
            'legalLinks' => [
                'agb' => route('pagesAgb', ['legal_lang' => $legalLocale], false),
                'avv' => route('pagesAvv', ['legal_lang' => $legalLocale], false),
                'privacy' => route('pagesPrivacy', ['legal_lang' => $legalLocale], false),
                'imprint' => route('pagesImprint', ['legal_lang' => $legalLocale], false),
            ],
            'legalLocaleSwitchUrls' => [
                'de' => $this->buildUrlWithQuery($currentPath, array_merge($request->query(), ['legal_lang' => 'de'])),
                'en' => $this->buildUrlWithQuery($currentPath, array_merge($request->query(), ['legal_lang' => 'en'])),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $query
     */
    private function buildUrlWithQuery(string $url, array $query): string
    {
        $normalized = [];
        foreach ($query as $key => $value) {
            if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        $queryString = http_build_query($normalized);

        return $queryString === '' ? $url : ($url . '?' . $queryString);
    }

    //View footer page: contact
    public function pagesContact(Request $request)
    {
        $name = "contact";
    
        try {
            $data['page'] = Page::select($name)->first();
        } catch (Exception $e) {
            return abort(404);
        }
    
        return view('pages', ['data' => $data, 'name' => $name]);
    }

    //Statistics of the number of clicks and links
    public function phpinfo()
    {
        return view('panel/phpinfo');
    }

    //Shows config file editor page
    public function showFileEditor(request $request)
    {
        return redirect('/admin/config');
    }

    //Saves advanced config
    public function editAC(request $request)
    {
        if ($request->ResetAdvancedConfig == 'RESET_DEFAULTS') {
            copy(base_path('storage/templates/advanced-config.php'), base_path('config/advanced-config.php')); 
        } else {
            file_put_contents('config/advanced-config.php', $request->AdvancedConfig);
        }

        return redirect('/admin/config#2');
    }

    //Saves .env config
    public function editENV(request $request)
    {
        $config = $request->altConfig;

        file_put_contents('.env', $config);

        return Redirect('/admin/config?alternative-config');
    }

    //Shows config file editor page
    public function showBackups(request $request)
    {
        return view('/panel/backups');
    }

    //Delete custom theme
    public function deleteTheme(request $request)
    {

        $del = $request->deltheme;

        if (empty($del)) {
            echo '<script type="text/javascript">';
            echo 'alert("No themes to delete!");';
            echo 'window.location.href = "../studio/theme";';
            echo '</script>';
        } else {

            $folderName = base_path() . '/themes/' . $del;



            function removeFolder($folderName)
            {
                if (File::exists($folderName)) {
                    File::deleteDirectory($folderName);
                    return true;
                }
            
                return false;
            }

            removeFolder($folderName);

            return Redirect('/admin/theme');
        }
    }

    //Shows config file editor page
    public function showConfig(request $request)
    {
        return view('/panel/config-editor');
    }

    //Shows config file editor page
    public function editConfig(request $request)
    {

        $type = $request->type;
        $entry = $request->entry;
        $value = $request->value;

        if($type === "toggle"){
            if($request->toggle != ''){$value = "true";}else{$value = "false";}
            if(EnvEditor::keyExists($entry)){EnvEditor::editKey($entry, $value);}
        } elseif($type === "toggle2") {
            if($request->toggle != ''){$value = "verified";}else{$value = "auth";}
            if(EnvEditor::keyExists($entry)){EnvEditor::editKey($entry, $value);}
        } elseif($type === "text") {
            if(EnvEditor::keyExists($entry)){EnvEditor::editKey($entry, '"' . $value . '"');}
        } elseif($type === "debug") {
            if($request->toggle != ''){
                if(EnvEditor::keyExists('APP_DEBUG')){EnvEditor::editKey('APP_DEBUG', 'true');}
                if(EnvEditor::keyExists('APP_ENV')){EnvEditor::editKey('APP_ENV', 'local');}
                if(EnvEditor::keyExists('LOG_LEVEL')){EnvEditor::editKey('LOG_LEVEL', 'debug');}
            } else {
                if(EnvEditor::keyExists('APP_DEBUG')){EnvEditor::editKey('APP_DEBUG', 'false');}
                if(EnvEditor::keyExists('APP_ENV')){EnvEditor::editKey('APP_ENV', 'production');}
                if(EnvEditor::keyExists('LOG_LEVEL')){EnvEditor::editKey('LOG_LEVEL', 'error');}
            }
        } elseif($type === "register") {
            if($request->toggle != ''){$register = "true";}else{$register = "false";}
            Page::first()->update(['register' => $register]);
        } elseif($type === "smtp") {
            if($request->toggle != ''){$value = "built-in";}else{$value = "smtp";}
            if(EnvEditor::keyExists('MAIL_MAILER')){EnvEditor::editKey('MAIL_MAILER', $value);}

            if(EnvEditor::keyExists('MAIL_HOST')){EnvEditor::editKey('MAIL_HOST', $request->MAIL_HOST);}
            if(EnvEditor::keyExists('MAIL_PORT')){EnvEditor::editKey('MAIL_PORT', $request->MAIL_PORT);}
            if(EnvEditor::keyExists('MAIL_USERNAME')){EnvEditor::editKey('MAIL_USERNAME', '"' . $request->MAIL_USERNAME . '"');}
            if(EnvEditor::keyExists('MAIL_PASSWORD')){EnvEditor::editKey('MAIL_PASSWORD', '"' . $request->MAIL_PASSWORD . '"');}
            if(EnvEditor::keyExists('MAIL_ENCRYPTION')){EnvEditor::editKey('MAIL_ENCRYPTION', $request->MAIL_ENCRYPTION);}
            if(EnvEditor::keyExists('MAIL_FROM_ADDRESS')){EnvEditor::editKey('MAIL_FROM_ADDRESS', $request->MAIL_FROM_ADDRESS);}
        } elseif($type === "homeurl") {
            if($request->value == 'default'){$value = "";}else{$value = '"' . $request->value . '"';}
            if(EnvEditor::keyExists($entry)){EnvEditor::editKey($entry, $value);}
        } elseif($type === "maintenance") {
            if($request->toggle != ''){$value = "true";}else{$value = "false";}
            if(file_exists(base_path("storage/MAINTENANCE"))){unlink(base_path("storage/MAINTENANCE"));}
            if(EnvEditor::keyExists($entry)){EnvEditor::editKey($entry, $value);}
        } else {
            if(EnvEditor::keyExists($entry)){EnvEditor::editKey($entry, $value);}
        }




        return Redirect('/admin/config');
    }
    
    //Shows theme editor page
    public function showThemes(request $request)
    {
        return view('/panel/theme');
    }

    //Show info about link
    public function redirectInfo(request $request)
    {
        $linkId = $request->id;

        if (empty($linkId)) {
            return abort(404);
        }
        
        $linkData = Link::find($linkId);

        if (empty($linkData)) {
            return abort(404);
        }

        function isValidLink($url) {
            $validPrefixes = array('http', 'https', 'ftp', 'mailto', 'tel', 'news');
        
            $pattern = '/^(' . implode('|', $validPrefixes) . '):/i';
        
            if (preg_match($pattern, $url) && strlen($url) <= 155) {
                return $url;
            } else {
                return "N/A";
            }
        }

        $link = isValidLink($linkData->link);

        $userID = $linkData->user_id;
        $userData = User::find($userID);

        return view('linkinfo', ['linkID' => $linkId, 'link' => $link, 'id' => $userID, 'userData' => $userData]);

    }

}
