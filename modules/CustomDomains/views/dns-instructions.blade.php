<p class="text-uppercase text-muted small mb-2">{{ __('Set up a custom domain') }}</p>
<ol class="mb-0 ps-3">
    <li class="mb-3">
        <p class="mb-1">
            {{ __('Recommended: Enter') }} <code>{{ __('www.yourdomain.com') }}</code> {{ __('in Wayvio.') }}
        </p>
        <p class="mb-1 text-muted small">
            {{ __('Your page will only be reachable under this variant. To make') }} <code>{{ __('yourdomain.com') }}</code> {{ __('work as well, set up a redirect at your provider – this is explained in step 4.') }}
        </p>
        <p class="mb-0 text-muted small">
            {{ __('Only use without www if your DNS provider supports ALIAS, ANAME or CNAME flattening.') }}
        </p>
    </li>
    <li class="mb-3">
        <p class="mb-1">{{ __('Create a CNAME record at your DNS provider:') }}</p>
        <p class="mb-1">
            <code>{{ __('www.yourdomain.com') }}</code> → <strong>customers.wayvio.de</strong>
        </p>
        <p class="mb-0 text-muted small">{{ __('Make sure no other record exists for this host – otherwise the CNAME cannot be created.') }}</p>
    </li>
    <li class="mb-3">
        <p class="mb-1">{{ __('Save the domain in Wayvio. The status may initially show as “pending” – this is normal.') }}</p>
        <p class="mb-0 text-muted small">{{ __('Only one domain per page is allowed. Remove the existing domain before adding a new one.') }}</p>
    </li>
    <li>
        <p class="mb-1">{{ __('Click Verify once the DNS change has propagated.') }}</p>
        <p class="mb-1 text-muted small">{{ __('Then set up a redirect for the other variant so your domain is reachable under both versions:') }}</p>
        <ul class="mb-0">
            <li>{{ __('Using www → redirect :root to :www', ['root' => __('yourdomain.com'), 'www' => __('www.yourdomain.com')]) }}</li>
            <li>{{ __('Using no www → redirect :www to :root', ['www' => __('www.yourdomain.com'), 'root' => __('yourdomain.com')]) }}</li>
        </ul>
    </li>
</ol>
<p class="text-muted small mt-2 mb-0">{{ __('This usually takes about 5 minutes, but can take up to 30 minutes.') }}</p>
