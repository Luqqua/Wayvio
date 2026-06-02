<?php

namespace App\Models;

use App\Services\Tenancy\TenantResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Link extends Model
{
    use HasFactory;

    protected $fillable = ['link', 'title', 'button_id', 'type_params', 'type', 'custom_icon', 'is_adult', 'tenant_owner_user_id'];

    protected $casts = [
        'is_disabled' => 'boolean',
        'is_adult' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($link) {
          if (config('linkstack.disable_random_link_ids') != 'true') {
            $numberOfDigits = config('linkstack.link_id_length') ?? 9;

            $minIdValue = 10**($numberOfDigits - 1);
            $maxIdValue = 10**$numberOfDigits - 1;

            do {
                $randomId = rand($minIdValue, $maxIdValue);
            } while (Link::find($randomId));

            $link->id = $randomId;
          }

          if (Schema::hasColumn('links', 'tenant_owner_user_id') && (int) ($link->tenant_owner_user_id ?? 0) <= 0) {
              $tenantOwnerId = function_exists('currentTenantOwnerId') ? currentTenantOwnerId() : null;

              if ($tenantOwnerId === null) {
                  $resourceUserId = (int) ($link->user_id ?? 0);
                  if ($resourceUserId > 0) {
                      try {
                          $tenantOwnerId = app(TenantResolver::class)->ownerIdForUserId($resourceUserId);
                      } catch (\Throwable $e) {
                          $tenantOwnerId = $resourceUserId;
                      }
                  }
              }

              if (is_int($tenantOwnerId) && $tenantOwnerId > 0) {
                  $link->tenant_owner_user_id = $tenantOwnerId;
              }
          }
        });

        if (Schema::hasColumn('links', 'is_disabled')) {
            static::addGlobalScope('enabled', function ($query) {
                $query->where('is_disabled', false);
            });
        }
    }

    public function scopeWithDisabled($query)
    {
        return $query->withoutGlobalScope('enabled');
    }
}
