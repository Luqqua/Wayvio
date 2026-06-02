@extends('help.article-layout')
@section('meta_title', 'Account & Billing — plan, invoices, 2FA & cancellation | ' . config('app.name'))
@section('meta_description', 'Upgrade or downgrade plan, download invoices, change payment method, set up two-factor authentication and cancel account.')
@section('breadcrumb_trail')<span class="here">Account &amp; Billing</span>@endsection
@section<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M3 21a9 9 0 0118 0"/></svg>@endsection
@section('art_title')Account & Billing — plan, invoices, security and cancellation.@endsection
@section('art_lede')
  Everything about your {{ config('app.name') }} account: upgrade or downgrade plans, download invoices, update payment method, set up two-factor authentication and cancel your account if needed.
@endsection
@section('content')
  <h2 id="plan-change">Upgrading or downgrading plans</h2>
  <p>Manage your plan under <strong>Settings → Subscription</strong>. There you can see your current plan, all available options and the next billing date.</p>
  <h3>Upgrade</h3>
  <p>Upgrades take effect immediately. You pay pro-rata for the remaining days of the current billing period.</p>
  <h3>Downgrade</h3>
  <p>Downgrades take effect at the end of the current billing period. Until then you retain all features of your current plan.</p>
  <div class="callout tip"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div><div><h4>Annual plan — cheaper than month by month</h4><p>The annual subscription is billed once a year and is more cost-effective compared to monthly billing. You can switch between monthly and annual billing at any time.</p></div></div>
  <h2 id="invoices">Invoices &amp; payment methods</h2>
  <p>All invoices are available under <strong>Settings → Billing → Invoice history</strong> as PDF downloads. To change your payment method, go to <strong>Settings → Billing → Payment method</strong>.</p>
  <table>
    <thead><tr><th>Payment option</th><th>Availability</th></tr></thead>
    <tbody>
      <tr><td>Credit card (Visa / Mastercard)</td><td>All plans</td></tr>
      <tr><td>SEPA direct debit</td><td>All plans (DACH bank accounts)</td></tr>
      <tr><td>PayPal</td><td>Monthly plans only</td></tr>
    </tbody>
  </table>
  <h2 id="2fa">Two-factor authentication (2FA)</h2>
  <p>Enable 2FA under <strong>Settings → Security → Two-factor authentication</strong>:</p>
  <ol>
    <li>Click <em>Enable 2FA</em>.</li>
    <li>Scan the QR code with an authenticator app (e.g. Google Authenticator, Authy or 1Password).</li>
    <li>Enter the 6-digit code to confirm setup.</li>
    <li>Save the <strong>backup codes</strong> securely offline.</li>
  </ol>
  <div class="callout warn"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><div><h4>Store backup codes safely</h4><p>If you lose your device and have no backup codes, you may lose access to your account. Store codes in a password manager or print and secure them.</p></div></div>
  <h2 id="cancellation">Cancellation &amp; data export</h2>
  <p>Export all your data first under <strong>Settings → Privacy → Export data</strong> — you'll receive a ZIP file with all pages, blocks, media and form submissions in JSON format. Cancel your account under <strong>Settings → Subscription → Cancel</strong>. Cancellation takes effect at the end of the current billing period.</p>
@endsection
@section('related')
  <a href="{{ route('help.en.quickstart.user') }}" class="rel"><span class="mini">Getting Started</span><h4>Quick start for users</h4><p>6 min · Step-by-step</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.quickstart.agency') }}" class="rel"><span class="mini">Agency</span><h4>Quick start for agencies</h4><p>8 min · Step-by-step</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.faq') }}" class="rel"><span class="mini">Q&amp;A</span><h4>FAQ</h4><p>All answers</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
@endsection
