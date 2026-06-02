<?php

namespace Modules\CustomDomains\Http\Middleware;

use App\Http\Controllers\UserController;
use App\Models\AgencyHub;
use App\Models\User;
use App\Services\Lifecycle\AccountLifecycleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\CustomDomains\Models\UserCustomDomain;

class DomainRoutingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $host = strtolower((string) $request->getHost());
        $mappingQuery = UserCustomDomain::query()
            ->whereRaw('LOWER(domain) = ?', [$host])
            ->where('status', 'verified');

        $mappings = $mappingQuery
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($mappings->count() > 1) {
            Log::critical('Duplicate verified custom domain mapping detected; refusing to route host', [
                'host' => $host,
            ]);

            return $this->renderUnavailablePage($request);
        }

        $mapping = $mappings->first();
        $forceCustomDomainHttps = app()->environment('production') || env('FORCE_ROUTE_HTTPS') === 'true';

        if (!$mapping) {
            return $next($request);
        }

        if (
            Schema::hasColumn('user_custom_domains', 'lifecycle_status')
            && (string) ($mapping->lifecycle_status ?? 'active') !== 'active'
        ) {
            return $this->renderUnavailablePage($request);
        }

        $isSecure = $request->isSecure() || $request->header('X-Forwarded-Proto') === 'https';
        if ($forceCustomDomainHttps && !$isSecure) {
            return redirect()->secure($request->getRequestUri());
        }

        if ($mapping->page_id) {
            $response = $this->renderPageScopedDomain($request, (int) $mapping->page_id);
            if ($response) {
                return $response;
            }

            if (!$this->isAllowedCustomDomainPath($request)) {
                abort(404);
            }

            return $next($request);
        }

        $response = $this->renderAgencyDomain($request, (int) $mapping->user_id);
        if ($response) {
            return $response;
        }

        if (!$this->isAllowedCustomDomainPath($request)) {
            abort(404);
        }

        return $next($request);
    }

    private function renderPageScopedDomain(Request $request, int $pageUserId)
    {
        if ($this->pageUnavailable($pageUserId)) {
            return $this->renderUnavailablePage($request);
        }

        $user = User::find($pageUserId);
        if (!$user) {
            abort(404);
        }

        $slug = trim((string) ($user->littlelink_name ?? ''));
        if ($slug === '') {
            abort(404);
        }

        $path = trim($request->getPathInfo(), '/');

        if ($path === '' || preg_match('/^p\/[A-Za-z0-9._-]+$/', $path) === 1 || $path === $slug) {
            if ($path !== '') {
                return redirect('/');
            }

            $request->route()?->setParameter('littlelink', $slug);
            $request->merge(['littlelink' => $slug]);
            $response = app(UserController::class)->littlelink($request);
            return app('router')->prepareResponse($request, $response);
        }

        if ($path === 'impressum') {
            return redirect('/imprint', 301);
        }

        if ($path === 'imprint') {
            $request->route()?->setParameter('littlelink', $slug);
            $request->merge(['littlelink' => $slug]);
            $response = app(UserController::class)->imprint($request);
            return app('router')->prepareResponse($request, $response);
        }

        if (in_array($path, ['datenschutz', 'datenschutzerklaerung'], true)) {
            return redirect('/privacy', 301);
        }

        if ($path === 'privacy') {
            $request->route()?->setParameter('littlelink', $slug);
            $request->merge(['littlelink' => $slug]);
            $response = app(UserController::class)->privacy($request);
            return app('router')->prepareResponse($request, $response);
        }

        if (preg_match('/^' . preg_quote($slug, '/') . '\/(?:imprint|impressum)$/', $path) === 1) {
            return redirect('/imprint', 301);
        }

        if (preg_match('/^' . preg_quote($slug, '/') . '\/(?:privacy|datenschutz|datenschutzerklaerung)$/', $path) === 1) {
            return redirect('/privacy', 301);
        }

        return null;
    }

    private function renderAgencyDomain(Request $request, int $agencyUserId)
    {
        if ($agencyUserId <= 0) {
            return null;
        }
        if (!Schema::hasTable('agency_hubs')) {
            return null;
        }

        $path = trim($request->getPathInfo(), '/');
        $ownerSlug = trim((string) User::query()->where('id', $agencyUserId)->value('littlelink_name'));
        if ($ownerSlug !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $ownerSlug) !== 1) {
            $ownerSlug = '';
        }

        if ($path === '') {
            if ($ownerSlug === '') {
                abort(404);
            }

            return $this->renderProfile($request, $ownerSlug);
        }

        if (in_array($path, ['imprint', 'privacy'], true)) {
            if ($ownerSlug === '') {
                abort(404);
            }

            return $this->renderProfile($request, $ownerSlug, $path);
        }

        if ($path === 'impressum') {
            return redirect('/imprint', 301);
        }

        if (in_array($path, ['datenschutz', 'datenschutzerklaerung'], true)) {
            return redirect('/privacy', 301);
        }

        if (preg_match('/^p\/([A-Za-z0-9._-]+)$/', $path, $matches) === 1) {
            return redirect('/' . $matches[1]);
        }

        $slug = null;
        $legalPage = null;
        $legalNeedsRedirect = false;
        if (preg_match('/^([A-Za-z0-9._-]+)\/(imprint|impressum|privacy|datenschutz|datenschutzerklaerung)$/', $path, $matches) === 1) {
            $slug = $matches[1];
            $legalSegment = $matches[2] ?? '';
            $legalPage = in_array($legalSegment, ['privacy', 'datenschutz', 'datenschutzerklaerung'], true)
                ? 'privacy'
                : 'imprint';
            $legalNeedsRedirect = $legalSegment !== $legalPage;
        } elseif (preg_match('/^([A-Za-z0-9._-]+)$/', $path, $matches) === 1) {
            $slug = $matches[1];
        }

        if (!$slug) {
            return null;
        }

        if ($ownerSlug !== '' && $slug === $ownerSlug) {
            if ($this->pageUnavailable($agencyUserId)) {
                return $this->renderUnavailablePage($request);
            }

            if ($legalPage === null) {
                return redirect('/');
            }

            if ($legalNeedsRedirect) {
                return redirect('/' . $legalPage, 301);
            }

            return redirect('/' . $legalPage, 301);
        }

        $hub = AgencyHub::query()
            ->where('agency_user_id', $agencyUserId)
            ->whereHas('managedUser', function ($query) use ($slug): void {
                $query->where('littlelink_name', $slug);
            })
            ->first(['managed_user_id', 'status']);

        if (!$hub) {
            return null;
        }

        if ((string) $hub->status !== 'active') {
            return $this->renderUnavailablePage($request);
        }

        $managedUserId = (int) ($hub->managed_user_id ?? 0);
        if ($managedUserId <= 0 || $this->pageUnavailable($managedUserId)) {
            return $this->renderUnavailablePage($request);
        }

        if ($legalNeedsRedirect) {
            return redirect('/' . $slug . '/' . $legalPage, 301);
        }

        return $this->renderProfile($request, $slug, $legalPage);
    }

    private function renderProfile(Request $request, string $slug, ?string $legalPage = null)
    {
        $request->route()?->setParameter('littlelink', $slug);
        $request->merge(['littlelink' => $slug]);

        $controller = app(UserController::class);
        $response = match ($legalPage) {
            'imprint' => $controller->imprint($request),
            'privacy' => $controller->privacy($request),
            default => $controller->littlelink($request),
        };

        return app('router')->prepareResponse($request, $response);
    }

    private function isAllowedCustomDomainPath(Request $request): bool
    {
        $path = $request->getPathInfo();
        if ($path === '/') {
            return true;
        }

        $routeName = $request->route()?->getName();
        if (in_array($routeName, ['clickNumber', 'report', 'report.submit', 'forms.submit', 'vcard', 'userRedirect', 'block.asset'], true)) {
            return true;
        }

        $allowedPrefixes = [
            '/assets/',
            '/storage/',
            '/themes/',
            '/img/',
            '/images/',
            '/fonts/',
            '/going/',
            '/forms/',
        ];
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        if (preg_match('/\\.(css|js|map|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot|otf|txt|xml|json)$/i', $path)) {
            return true;
        }

        if (in_array($path, ['/favicon.ico', '/robots.txt', '/sitemap.xml', '/manifest.json', '/site.webmanifest'], true)) {
            return true;
        }

        return false;
    }

    private function pageUnavailable(int $userId): bool
    {
        return app(AccountLifecycleService::class)->publicPageUnavailableReason($userId) !== null;
    }

    private function renderUnavailablePage(Request $request)
    {
        return response()->view('wayvio.unavailable', [], 200);
    }
}
