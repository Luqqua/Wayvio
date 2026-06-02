<?php

namespace Modules\Tiers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'tier_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_status',
        'cancel_at_period_end',
        'pending_tier_id',
        'pending_hub_slots_included',
        'hub_slots_included',
        'hub_slots_addon',
        'lifecycle_last_tier_id',
        'lifecycle_last_hub_slots_included',
        'payment_failed_at',
        'payment_warning_sent_at',
        'payment_restricted_at',
        'payment_deletion_warning_sent_at',
        'payment_pending_deletion_at',
        'payment_delete_after_at',
        'agency_grace_period_started_at',
        'agency_grace_period_ends_at',
        'agency_over_quota_count',
        'hub_inventory_over_quota_since',
        'hub_inventory_over_quota_count',
        'expires_at',
    ];
    protected $dates = [
        'expires_at',
        'payment_failed_at',
        'payment_warning_sent_at',
        'payment_restricted_at',
        'payment_deletion_warning_sent_at',
        'payment_pending_deletion_at',
        'payment_delete_after_at',
        'agency_grace_period_started_at',
        'agency_grace_period_ends_at',
        'hub_inventory_over_quota_since',
    ];
    protected $casts = [
        'cancel_at_period_end' => 'boolean',
        'pending_tier_id' => 'integer',
        'pending_hub_slots_included' => 'integer',
        'hub_slots_included' => 'integer',
        'hub_slots_addon' => 'integer',
        'lifecycle_last_tier_id' => 'integer',
        'lifecycle_last_hub_slots_included' => 'integer',
        'agency_over_quota_count' => 'integer',
        'hub_inventory_over_quota_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }

    public function pendingTier(): BelongsTo
    {
        return $this->belongsTo(Tier::class, 'pending_tier_id');
    }
}
