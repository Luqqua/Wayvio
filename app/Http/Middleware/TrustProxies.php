<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    public function __construct()
    {
        $this->proxies = config('trustedproxy.proxies');

        // Allow tunnel/proxy headers in local development (e.g. ngrok)
        // when no explicit TRUSTED_PROXIES list is configured.
        if ($this->proxies === null && app()->environment('local')) {
            $this->proxies = '*';
        }

        $this->headers = (int) config('trustedproxy.headers', $this->headers);
    }

    /**
     * The trusted proxies for this application.
     *
     * @var array|string|null
     */
    protected $proxies = null;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX
        | Request::HEADER_X_FORWARDED_AWS_ELB;
}
