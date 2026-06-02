<?php

namespace Modules\AdminExtensions\Models;

use Illuminate\Database\Eloquent\Model;

class StripeWebhookLog extends Model
{
    protected $fillable = ['event_id', 'type', 'payload', 'status'];
    protected $casts = ['payload' => 'array'];
}
