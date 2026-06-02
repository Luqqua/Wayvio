<?php

namespace Modules\Partners\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerInviteCode extends Model
{
    protected $fillable = [
        'partner_user_id',
        'code',
        'status',
        'expires_at',
        'max_uses',
        'uses_count',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
