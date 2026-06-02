<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsResourceState extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_owner_user_id',
        'status',
        'reason',
        'suspended_at',
        'pending_deletion_at',
        'delete_after_at',
        'deleted_at',
    ];

    protected $casts = [
        'suspended_at' => 'datetime',
        'pending_deletion_at' => 'datetime',
        'delete_after_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
