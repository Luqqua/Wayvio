<?php

namespace Modules\Partners\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerCommissionLedger extends Model
{
    protected $table = 'partner_commission_ledger';

    protected $fillable = [
        'partner_user_id',
        'referred_user_id',
        'partner_attribution_id',
        'billing_record_id',
        'source_event_id',
        'source_invoice_id',
        'entry_type',
        'gross_amount_cents',
        'commission_amount_cents',
        'currency',
        'status',
        'available_at',
        'payout_batch_id',
        'meta',
    ];

    protected $casts = [
        'available_at' => 'datetime',
        'meta' => 'array',
    ];
}
