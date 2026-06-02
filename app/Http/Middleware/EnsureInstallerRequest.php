<?php

namespace App\Http\Middleware;

use App\Support\Security\IpAddressMatcher;
use Closure;
use Illuminate\Http\Request;

class EnsureInstallerRequest
{
    public function handle(Request $request, Closure $next)
    {
        $hasInstallerMarker = file_exists(base_path('INSTALLING')) || file_exists(base_path('INSTALLERLOCK'));
        if (!$hasInstallerMarker) {
            abort(404, 'Installer is not available');
        }

        if (file_exists(base_path('storage/app/ISINSTALLED'))) {
            abort(503, 'Installer markers are present on an already installed application');
        }

        if (!((bool) config('installer.enabled', false))) {
            abort(503, 'Installer is disabled');
        }

        $sourceIp = IpAddressMatcher::requestIp($request);
        if ($sourceIp === '') {
            abort(403, 'Unable to resolve source IP for installer request');
        }

        $requireLoopback = (bool) config('installer.require_loopback', true);
        $allowedIps = IpAddressMatcher::sanitizeAllowlist(
            (array) config('installer.allowed_ips', ['127.0.0.1', '::1']),
        );

        if ($requireLoopback) {
            if (!IpAddressMatcher::isLoopback($sourceIp)) {
                abort(403, 'Installer requires loopback source');
            }
        } elseif (!IpAddressMatcher::isAllowed($sourceIp, $allowedIps)) {
            abort(403, 'Source IP not allowed for installer');
        }

        $expectedSecret = trim((string) config('installer.one_time_secret', ''));
        if ($expectedSecret === '') {
            abort(503, 'Installer one-time secret is not configured');
        }

        $session = $request->session();
        if (!$session->get('installer.secret_verified', false)) {
            $providedSecret = trim((string) (
                $request->header('X-Installer-Secret')
                ?? $request->query('installer_secret')
                ?? $request->input('installer_secret')
                ?? ''
            ));

            if ($providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
                abort(403, 'Invalid installer secret');
            }

            $session->put('installer.secret_verified', true);
        }

        return $next($request);
    }
}
