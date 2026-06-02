<?php

namespace Modules\AnalyticsPremium\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEventExtended extends Model
{
    public $timestamps = false;
    protected $table = 'analytics_events_extended';

    protected $fillable = [
        'base_event_id', 'user_id', 'tenant_owner_user_id', 'page_id', 'link_id',
        'device_type', 'browser', 'operating_system', 'country', 'referrer', 'created_at',
    ];
}
