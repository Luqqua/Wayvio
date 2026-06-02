@extends('help.article-layout')
@section('meta_title', 'White-Label — your own logo & branding on client hubs | ' . config('app.name'))
@section('meta_description', 'White-label setup for agencies: your own logo, colours and domain on all client hubs. How to completely remove Wayvio branding.')
@section('breadcrumb_trail')<span class="here">White-Label</span>@endsection
@section<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21a6 6 0 0112 0"/><path d="M14 21a5 5 0 017-4.6"/></svg>@endsection
@section('art_title')White-Label — your own logo and branding on all client hubs.@endsection
@section('art_lede')
  With the white-label feature you replace the {{ config('app.name') }} branding with your own agency brand. Clients only see your logo, your colours and your name — {{ config('app.name') }} stays invisible in the background.
@endsection
@section('content')
  <div class="callout tip"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div><div><h4>White-label is an agency feature</h4><p>White-label branding is exclusively available in the agency plan. Upgrade under <strong>Settings → Subscription</strong> if you don't yet have an agency account.</p></div></div>
  <h2 id="what-is-white-label">What white-label means</h2>
  <p>In the standard setup, clients see the {{ config('app.name') }} logo in the login and dashboard interface. With white-label you replace that with your own branding — completely and everywhere:</p>
  <ul>
    <li>Login page and dashboard show <strong>your logo and colours</strong></li>
    <li>Email notifications to clients come <strong>from your domain</strong></li>
    <li>The help centre link can point to <strong>your own support URL</strong></li>
    <li>{{ config('app.name') }} doesn't appear anywhere in the client interface</li>
  </ul>
  <h2 id="setup">Setting up white-label</h2>
  <h3>Step 1: Activate white-label</h3>
  <p>Open <strong>Settings → White-Label</strong> in the agency dashboard and enable the feature with the toggle at the top.</p>
  <h3>Step 2: Upload logo</h3>
  <p>Upload two logo variants: a <strong>light logo</strong> (used on white/light backgrounds) and a <strong>dark logo</strong> (used on dark backgrounds and in emails). Recommended formats: SVG or PNG with transparent background. Minimum width: 200 px.</p>
  <h3>Step 3: Set primary colour</h3>
  <p>Under <strong>White-Label → Colours</strong> set a primary colour for buttons, links and accents in the client interface. Use your main branding colour.</p>
  <h3>Step 4: Sender domain for emails</h3>
  <p>For emails to come from your domain instead of <code>@example.com</code>:</p>
  <ol>
    <li>Enter your domain under <strong>White-Label → Email</strong> (e.g. <code>mail.youragency.com</code>).</li>
    <li>Add the displayed DNS records (<code>SPF</code>, <code>DKIM</code>, optional <code>DMARC</code>) at your domain provider.</li>
    <li>Click <em>Verify domain</em>. After successful verification, all client emails are sent from your domain.</li>
  </ol>
  <div class="callout warn"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><div><h4>SPF and DKIM are required for deliverability</h4><p>Without correctly set SPF and DKIM records, emails from your domain will land in recipients' spam folders. Fully verify the domain before activating email sending.</p></div></div>
  <h3>Step 5: Customise support link</h3>
  <p>Under <strong>White-Label → Support</strong> set the URL for the "Help" link in the client dashboard. Enter your own support email, ticketing system or help page — so client support requests go directly to you.</p>
  <h2 id="own-login-domain">Optional: custom login domain</h2>
  <p>For the complete white-label experience, set up your own sub-domain as the login URL for your clients — e.g. <code>app.youragency.com</code> instead of <code>app.wayvio.app</code>.</p>
  <ol>
    <li>Enter the desired sub-domain under <strong>White-Label → Login domain</strong>.</li>
    <li>Set a CNAME at your domain provider: <code>app.youragency.com</code> → <code>cname.wayvio.app</code>.</li>
    <li>After DNS propagation and automatic SSL setup, your login URL is active.</li>
  </ol>
  <h2 id="what-remains-visible">What remains visible after white-label?</h2>
  <table>
    <thead><tr><th>Area</th><th>Without white-label</th><th>With white-label</th></tr></thead>
    <tbody>
      <tr><td>Login page</td><td>{{ config('app.name') }} logo</td><td>Your logo</td></tr>
      <tr><td>Dashboard header</td><td>{{ config('app.name') }} logo</td><td>Your logo</td></tr>
      <tr><td>Email sender</td><td><code>@example.com</code></td><td>Your domain</td></tr>
      <tr><td>Support link</td><td>{{ config('app.name') }} help centre</td><td>Your link</td></tr>
      <tr><td>Client hub (public)</td><td>No branding visible</td><td>No branding visible</td></tr>
    </tbody>
  </table>
@endsection
@section('related')
  <a href="{{ route('help.en.quickstart.agency') }}" class="rel"><span class="mini">Agency</span><h4>Agency quick start</h4><p>8 min · Step-by-step</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.account-abrechnung') }}" class="rel"><span class="mini">Account &amp; Billing</span><h4>Upgrade plan</h4><p>4 min · Guide</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
@endsection
