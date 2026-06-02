<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantResolver;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantResolutionException;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ResolveTenantContext
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldResolveForRequest($request)) {
            $isTenantProtectedRoute = $this->isTenantProtectedRoute($request);

            try {
                $context = $this->tenantResolver->resolveForRequest($request, $isTenantProtectedRoute);
                if ($context instanceof TenantContext) {
                    $request->attributes->set('tenant_context', $context);
                    app()->instance(TenantContext::class, $context);
                }
            } catch (TenantResolutionException $e) {
                $this->logFailure($request, $e);

                if ($isTenantProtectedRoute) {
                    abort($e->statusCode(), 'Tenant context validation failed.');
                }
            }
        }

        return $next($request);
    }

    private function shouldResolveForRequest(Request $request): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return $request->route() !== null;
    }

    private function isTenantProtectedRoute(Request $request): bool
    {
        $route = $request->route();
        if (!$route) {
            return false;
        }

        $middleware = $route->gatherMiddleware();

        return in_array('auth', $middleware, true)
            || in_array(Authenticate::class, $middleware, true);
    }

    private function logFailure(Request $request, TenantResolutionException $exception): void
    {
        Log::warning('Tenant context middleware fail-closed', array_merge([
            'reason_code' => $exception->reasonCode(),
            'route' => $this->routeLabel($request),
            'path' => $request->path(),
            'actor_user_id' => Auth::id(),
        ], $exception->context()));
    }

    private function routeLabel(Request $request): string
    {
        $route = $request->route();
        if (!$route) {
            return trim((string) $request->path(), '/');
        }

        return (string) ($route->getName() ?: $route->uri());
    }
}
