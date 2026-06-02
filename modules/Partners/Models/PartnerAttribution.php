<?php

namespace Modules\Partners\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerAttribution extends Model
{
    protected $fillable = [
        'referred_user_id',
        'partner_user_id',
        'tenant_owner_user_id',
        'invite_code_id',
        'source',
        'commission_rate_bps',
        'starts_at',
        'ends_at',
        'attributed_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'attributed_at' => 'datetime',
    ];
}
