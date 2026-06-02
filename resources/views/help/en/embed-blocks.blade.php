@extends('help.article-layout')

@section('meta_title', 'Embeds & Forms — Instagram, YouTube, Maps and contact forms | ' . config('app.name'))
@section('meta_description', 'Which embeds are allowed, how to handle consent for third parties, protect performance and set up a native contact form.')

@section('breadcrumb_trail')
  <span class="here">Embeds &amp; Forms</span>
@endsection
@section('art_title')Embeds &amp; Forms — embed content correctly and handle consent.@endsection
@section('art_lede')
  The embed block lets you integrate external content into your {{ config('app.name') }} page — from booking widgets to maps and social media feeds. This guide shows which embeds work, what to watch out for with consent, and how to set up a native contact form.
@endsection

@section('content')

  <h2 id="embed-block">How the embed block works</h2>
  <p>Add an embed block to your stack, paste the embed code or URL from the external provider, and {{ config('app.name') }} wraps it in a consent-aware container. The block is responsive and adapts to any screen width.</p>

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Use native blocks for simple content</h4>
      <p>Native blocks (link, profile, image) are faster and don't require consent handling. Only use the embed block when a third-party integration is genuinely needed.</p>
    </div>
  </div>

  <h2 id="supported-embeds">Supported embeds</h2>
  <table>
    <thead>
      <tr><th>Service</th><th>Embed type</th><th>Consent required</th></tr>
    </thead>
    <tbody>
      <tr><td>YouTube</td><td>Video player</td><td>Yes (marketing cookies)</td></tr>
      <tr><td>Vimeo</td><td>Video player</td><td>Yes</td></tr>
      <tr><td>Instagram</td><td>Post or profile</td><td>Yes</td></tr>
      <tr><td>Google Maps</td><td>Map</td><td>Yes (IP transfer)</td></tr>
      <tr><td>Spotify</td><td>Track or playlist</td><td>Yes</td></tr>
      <tr><td>Calendly</td><td>Booking widget</td><td>Depends on config</td></tr>
      <tr><td>Custom iFrame</td><td>Any URL</td><td>Check provider</td></tr>
    </tbody>
  </table>

  <h2 id="consent">Consent handling</h2>
  <p>{{ config('app.name') }} automatically shows a consent placeholder before loading embeds that set cookies or transfer data to third parties. The visitor sees a notice and can actively unlock the content. This meets ePrivacy and GDPR requirements.</p>

  <div class="callout warn">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div>
      <h4>Update privacy policy for new embeds</h4>
      <p>Every time you add a new third-party embed, your privacy policy must be updated accordingly. {{ config('app.name') }} helps with preparation — completeness is your responsibility as operator.</p>
    </div>
  </div>

  <h2 id="performance">Protecting page performance</h2>
  <ul>
    <li>Keep only essential embeds active — each one adds loading time.</li>
    <li>Place heavier embeds lower on the page (below the fold).</li>
    <li>Check mobile performance after every change.</li>
    <li>Too many simultaneous embeds can significantly delay First Contentful Paint.</li>
  </ul>

  <h2 id="contact-form">Set up a native contact form</h2>
  <p>The <strong>contact form block</strong> is a native {{ config('app.name') }} solution — no third-party service, no cookies. Add it to your stack, configure the fields you need (name, email, message) and submissions land directly in your dashboard.</p>
  <p>You receive email notifications for each new submission. Under <strong>Settings → Forms</strong> you can download all submissions as CSV.</p>

@endsection

@section('related')
  <a href="{{ route('help.en.rechtssicherheit') }}" class="rel">
    <span class="mini">Legal &amp; GDPR</span>
    <h4>Cookie consent &amp; third parties</h4>
    <p>6 min · Guide</p>
    <span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.en.quickstart.user') }}" class="rel">
    <span class="mini">Getting Started</span>
    <h4>Quick start for users</h4>
    <p>6 min · Step-by-step</p>
    <span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.en.faq') }}" class="rel">
    <span class="mini">Q&amp;A</span>
    <h4>FAQ</h4>
    <p>All answers</p>
    <span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
