<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class BillingNotificationOutbox extends Model
{
    protected $table = 'billing_notification_outbox';

    protected $fillable = [
        'user_id',
        'email',
        'template_key',
        'dedupe_key',
        'source_event_id',
        'source_event_type',
        'payload',
        'status',
        'attempt_count',
        'scheduled_for',
        'sent_at',
        'failed_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
