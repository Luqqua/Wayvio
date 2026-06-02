<?php

namespace App\Models;

use App\Support\EmailLocaleResolver;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail, HasLocalePreference
{
    use HasFactory, Notifiable;

    public const ROLE_USER = 'user';
    public const ROLE_VIP = 'vip';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_AGENCY_HUB = 'agency_hub';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'image',
        'password',
        'provider',
        'provider_id',
        'email_verified_at',
        'littlelink_name',
        'littlelink_description',
        'role',
        'block',
        'is_published',
        'published_at',
        'theme',
        'locale',
        'last_login_locale',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'pending_email_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'tos_accepted_at' => 'datetime',
        'agb_accepted_at' => 'datetime',
        'avv_accepted_at' => 'datetime',
        'meta_overrides' => 'array',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
        'account_status_changed_at' => 'datetime',
        'account_delete_after_at' => 'datetime',
        'account_deleted_at' => 'datetime',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'analytics_suspended_at' => 'datetime',
        'analytics_pending_deletion_at' => 'datetime',
        'analytics_delete_after_at' => 'datetime',
        'analytics_deleted_at' => 'datetime',
        'meta_tags_suspended_at' => 'datetime',
        'meta_tags_pending_deletion_at' => 'datetime',
        'meta_tags_delete_after_at' => 'datetime',
        'meta_tags_deleted_at' => 'datetime',
    ];

    public function decryptedTwoFactorSecret(): ?string
    {
        if (!$this->two_factor_secret) {
            return null;
        }

        try {
            return Crypt::decryptString($this->two_factor_secret);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function recoveryCodes(): array
    {
        if (!$this->two_factor_recovery_codes) {
            return [];
        }

        try {
            $codes = json_decode(Crypt::decryptString($this->two_factor_recovery_codes), true);
            return is_array($codes) ? $codes : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function visits()
    {
        return visits($this)->relation();
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function isAgencyHubAccount(): bool
    {
        return $this->role === self::ROLE_AGENCY_HUB;
    }

    public function preferredLocale(): string
    {
        return EmailLocaleResolver::resolve(
            is_string($this->locale) ? $this->locale : null,
            is_string($this->last_login_locale) ? $this->last_login_locale : null
        );
    }

    public function scopeWithoutAgencyHubAccounts(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereNull('role')
                ->orWhere('role', '!=', self::ROLE_AGENCY_HUB);
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (config('linkstack.disable_random_user_ids') != 'true') {
                if (is_null(User::first())) {
                    $user->id = 1;
                } else {
                    $numberOfDigits = config('linkstack.user_id_length') ?? 6;
    
                    $minIdValue = 10**($numberOfDigits - 1);
                    $maxIdValue = 10**$numberOfDigits - 1;
    
                    do {
                        $randomId = rand($minIdValue, $maxIdValue);
                    } while (User::find($randomId));
    
                    $user->id = $randomId;
                }
            }
        });

        // Default: share button is disabled until the user explicitly enables it.
        static::created(function ($user) {
            $currentShareButtonSetting = UserData::getData((int) $user->id, 'disable-sharebtn');
            if (in_array($currentShareButtonSetting, [null, 'null'], true)) {
                UserData::saveData((int) $user->id, 'disable-sharebtn', true);
            }
        });
    }
}
