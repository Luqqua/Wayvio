@extends('help.article-layout')

@section('meta_title', 'Connect your own domain — configure DNS correctly | ' . config('app.name'))
@section('meta_description', 'Step-by-step guide: connect your own domain, set DNS records, and let SSL certificates be issued automatically.')

@section('breadcrumb_trail')
  <span class="here">Domains &amp; SSL</span>
@endsection
@section('art_title')Connect your own domain with {{ config('app.name') }} — configure DNS correctly.@endsection
@section('art_lede')
  This guide walks you through connecting a domain like <code style="font-family:'Geist Mono',monospace;background:rgba(15,61,62,.06);padding:2px 8px;border-radius:6px;font-size:15px">yourdomain.com</code> to your {{ config('app.name') }} page. We'll cover DNS records for common providers and verify that automatic SSL is working correctly.
@endsection

@section('content')

  <p>Before you start: you need access to your <strong>{{ config('app.name') }} account</strong> and your domain provider's <strong>DNS management panel</strong>. The setup takes 5–10 minutes — DNS propagation can take up to 24 hours but is usually complete within minutes.</p>

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Don't have a domain yet?</h4>
      <p>You can run your {{ config('app.name') }} page on the free sub-domain first and add a custom domain later at any time.</p>
    </div>
  </div>

  <h2 id="step-1">1. Add domain in dashboard</h2>
  <p>Open <strong>Settings → Domains</strong> and click <em>Add domain</em>. Enter your domain and confirm.</p>

  <h2 id="step-2">2. Add CNAME record at your provider</h2>
  <p>Log in to your domain provider and open DNS management. Create the following record:</p>

  <table>
    <thead>
      <tr><th>Type</th><th>Host</th><th>Value</th><th>TTL</th></tr>
    </thead>
    <tbody>
      <tr><td><code>CNAME</code></td><td><code>www</code></td><td><code>cname.wayvio.app</code></td><td>3600</td></tr>
    </tbody>
  </table>

  <p>Your page will then be accessible at <code>https://www.yourdomain.com</code>.</p>

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Set up a redirect from the root domain</h4>
      <p>So visitors who type <code>yourdomain.com</code> (without www) also reach your page, set up a <strong>URL redirect</strong> from <code>yourdomain.com</code> to <code>www.yourdomain.com</code> at your provider. Look for an option called "Redirect", "URL Forwarding", or similar in your provider's control panel.</p>
    </div>
  </div>

  <h2 id="step-3">3. Verify connection &amp; SSL</h2>
  <p>Back in the dashboard, run the connection check. {{ config('app.name') }} verifies the CNAME record and automatically issues an SSL certificate.</p>
  <ol>
    <li>Click <em>Check connection</em> next to your domain.</li>
    <li>Wait 1–10 minutes — status changes from <code>Pending</code> to <code>Connected</code>.</li>
    <li>Once status shows <code>Connected · SSL active</code>, your page is live at <code>https://www.yourdomain.com</code>.</li>
  </ol>

  <h2 id="troubleshooting">Common issues</h2>
  <h3>Status stays on <code>Pending</code></h3>
  <p>DNS propagation can take up to 24 hours depending on provider. Check with online tools (e.g. whatsmydns.net) whether the CNAME record has propagated globally.</p>

  <h3>SSL certificate not issued</h3>
  <p>Make sure no <code>CAA</code> record is blocking Let's Encrypt. Delete such entries or add <code>letsencrypt.org</code> as an allowed CA.</p>

@endsection

@section('related')
  <a href="{{ route('help.en.quickstart.user') }}" class="rel">
    <span class="mini">Getting Started</span>
    <h4>Quick start for users</h4>
    <p>6 min · Step-by-step</p>
    <span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.en.faq') }}" class="rel">
    <span class="mini">Q&amp;A</span>
    <h4>DNS &amp; SSL FAQ</h4>
    <p>All answers</p>
    <span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
