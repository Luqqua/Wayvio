<?php

namespace Modules\Billing\Services;

use Modules\Billing\Models\BillingRecord;
use App\Models\User;

class BillingRecorder
{
    public function getForDashboard(User $user)
    {
        return BillingRecord::where('user_id', $user->id)->latest()->get();
    }
}
