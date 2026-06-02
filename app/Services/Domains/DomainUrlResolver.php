<?php

namespace App\Services\Domains;

use App\Models\AgencyHub;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Modules\CustomDomains\Models\UserCustomDomain;

class DomainUrlResolver
{
    public function profileUrlForEditor(User $owner, User $pageUser): string
    {
        $slug = $pageUser->littlelink_name ?: (string) $pageUser->id;

        if (Schema::hasTable('user_custom_domains')) {
            $hubDomain = UserCustomDomain::query()
                ->where('user_id', $owner->id)
                ->where('page_id', $pageUser->id)
                ->where('status', 'verified')
                ->when(Schema::hasColumn('user_custom_domains', 'lifecycle_status'), function ($query): void {
                    $query->where('lifecycle_status', 'active');
                })
                ->value('domain');

            if (is_string($hubDomain) && $hubDomain !== '') {
                return $this->absoluteDomainUrl($hubDomain);
            }

            $agencyDomain = UserCustomDomain::query()
                ->where('user_id', $owner->id)
                ->whereNull('page_id')
                ->where('status', 'verified')
                ->when(Schema::hasColumn('user_custom_domains', 'lifecycle_status'), function ($query): void {
                    $query->where('lifecycle_status', 'active');
                })
                ->value('domain');

            if (is_string($agencyDomain) && $agencyDomain !== '') {
                if ((int) $owner->id === (int) $pageUser->id) {
                    return $this->absoluteDomainUrl($agencyDomain);
                }

                return rtrim($this->absoluteDomainUrl($agencyDomain), '/') . '/' . ltrim($slug, '/');
            }
        }

        if (in_array($slug, reservedSlugs(), true)) {
            return $this->absoluteAppUrl('/p/' . $slug);
        }

        return $this->absoluteAppUrl('/' . $slug);
    }

    public function ownerForPageUser(User $pageUser): User
    {
        if (!Schema::hasTable('agency_hubs')) {
            return $pageUser;
        }

        $ownerId = AgencyHub::query()
            ->where('managed_user_id', $pageUser->id)
            ->where('status', 'active')
            ->value('agency_user_id');

        if (!$ownerId) {
            return $pageUser;
        }

        return User::query()->find($ownerId) ?? $pageUser;
    }

    private function absoluteDomainUrl(string $domain): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);
        if (!is_string($scheme) || $scheme === '') {
            $scheme = 'https';
        }

        return $scheme . '://' . trim($domain, '/');
    }

    private function absoluteAppUrl(string $path): string
    {
        $base = trim((string) config('app.url'));
        if ($base === '') {
            $base = (string) url('/');
        }

        if (!preg_match('#^https?://#i', $base)) {
            $base = 'https://' . ltrim($base, '/');
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}
