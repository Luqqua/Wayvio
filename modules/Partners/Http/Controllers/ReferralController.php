<?php

namespace Modules\Partners\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Partners\Services\PartnerManager;

class ReferralController extends Controller
{
    public function __construct(
        private readonly PartnerManager $partnerManager,
    ) {
    }

    public function capture(Request $request, string $code): RedirectResponse
    {
        $this->partnerManager->captureReferralCode($request, $code);

        return redirect($this->redirectTarget($request));
    }

    private function redirectTarget(Request $request): string
    {
        $requested = (string) $request->query('next', '');
        if ($requested !== '' && str_starts_with($requested, '/') && !str_starts_with($requested, '//')) {
            return $requested;
        }

        return route('register');
    }
}
