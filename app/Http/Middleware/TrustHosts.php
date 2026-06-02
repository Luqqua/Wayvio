<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\CustomDomains\Models\UserCustomDomain;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array
     */
    public function hosts()
    {
        return [
            $this->allSubdomainsOfApplicationUrl(),
            ...$this->customDomainHosts(),
        ];
    }

    private function customDomainHosts(): array
    {
        $hosts = [];
        $hosts = array_merge($hosts, $this->configCustomDomainHosts());
        $hosts = array_merge($hosts, $this->verifiedCustomDomainHosts());

        return array_values(array_unique($hosts));
    }

    private function configCustomDomainHosts(): array
    {
        $configDomains = config('advanced-config.custom_domains', []);
        if (!is_array($configDomains)) {
            return [];
        }

        $hosts = [];
        foreach ($configDomains as $config) {
            if (!is_array($config)) {
                continue;
            }
            $domain = $config['domain'] ?? null;
            if (!is_string($domain) || trim($domain) === '') {
                continue;
            }
            $hosts[] = '^' . preg_quote(strtolower(trim($domain, ". \t\n\r\0\x0B")), '/') . '$';
        }

        return $hosts;
    }

    private function verifiedCustomDomainHosts(): array
    {
        if (!class_exists(UserCustomDomain::class)) {
            return [];
        }

        try {
            if (!Schema::hasTable('user_custom_domains')) {
                return [];
            }

            return Cache::remember('custom-domains.trusted-hosts', now()->addMinute(), function () {
                return UserCustomDomain::query()
                    ->where('status', 'verified')
                    ->distinct()
                    ->pluck('domain')
                    ->filter()
                    ->map(fn (string $domain) => '^' . preg_quote($domain, '/') . '$')
                    ->values()
                    ->all();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }
}
