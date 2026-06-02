<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyHub extends Model
{
    protected $fillable = [
        'display_name',
    ];
    protected $casts = [
        'suspended_at' => 'datetime',
        'pending_deletion_at' => 'datetime',
        'delete_after_at' => 'datetime',
        'deleted_at_lifecycle' => 'datetime',
    ];

    public function agencyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agency_user_id');
    }

    public function managedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_user_id');
    }
}
