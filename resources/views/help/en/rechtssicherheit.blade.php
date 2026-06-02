@extends('help.article-layout')
@section('meta_title', 'Legal & GDPR — Imprint, Privacy Policy, Cookie Consent | ' . config('app.name'))
@section('meta_description', 'Imprint generator, automatic privacy policy and cookie consent setup. Tools to help you prepare — final review is your responsibility.')
@section('breadcrumb_trail')<span class="here">Legal &amp; GDPR</span>@endsection
@section<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>@endsection
@section('art_title')Legal & GDPR — imprint, privacy policy and cookie consent.@endsection
@section('art_lede')
  {{ config('app.name') }} provides tools for imprint, privacy policy and cookie consent. These generators help you prepare — completeness and accuracy is your responsibility as operator. When in doubt, consult a lawyer.
@endsection
@section('content')
  <div class="callout warn"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><div><h4>Not legal advice</h4><p>{{ config('app.name') }} is not a lawyer and does not replace legal consultation. The tools and information provided are for orientation only. For binding advice on your specific situation, consult a qualified lawyer.</p></div></div>
  <h2 id="imprint">Using the imprint generator</h2>
  <p>In many countries, commercial and semi-commercial websites are legally required to display an imprint with full contact details. Go to <strong>Settings → Legal → Imprint</strong> and fill in all required fields:</p>
  <ul>
    <li><strong>Name &amp; address</strong> — full postal address of the responsible person</li>
    <li><strong>Contact</strong> — email address (required), phone number (recommended)</li>
    <li><strong>Company registration / VAT ID</strong> — if applicable</li>
  </ul>
  <p>The generated imprint is automatically created as a separate page and linked in your page footer.</p>
  <h2 id="privacy">Generating a privacy policy</h2>
  <p>GDPR requires operators to transparently inform visitors about personal data processing. {{ config('app.name') }} automatically generates a base privacy policy based on your active blocks and embeds.</p>
  <h3>What is detected automatically</h3>
  <ul>
    <li>Native contact form → processing notice for form data</li>
    <li>Embedded videos (YouTube / Vimeo) → third-party data transfer notice</li>
    <li>Google Maps → IP transfer to Google notice</li>
    <li>Hosting infrastructure → standard server log section</li>
  </ul>
  <div class="callout tip"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div><div><h4>Update privacy policy after changes</h4><p>Whenever you add a new third-party embed, regenerate your privacy policy. It must cover all active data processors.</p></div></div>
  <h2 id="cookie-consent">Cookie consent &amp; third parties</h2>
  <p>As soon as your page loads external scripts that may set cookies, visitor consent is required — especially for: YouTube / Vimeo, Google Maps, Instagram embeds, third-party booking widgets, and analytics tools.</p>
  <p>{{ config('app.name') }} automatically shows a consent placeholder for blocked embeds: the visitor sees a notice and can actively unlock the content. This meets ePrivacy and GDPR requirements.</p>
  <h3>Activating the consent banner</h3>
  <p>Under <strong>Settings → Legal → Cookie Consent</strong> you can configure the consent banner — categories (Essential, Statistics, Marketing) and the displayed text. The banner appears automatically on first visit.</p>
  <h2 id="responsibility">Responsibility &amp; disclaimer</h2>
  <table>
    <thead><tr><th>Content</th><th>Responsibility</th></tr></thead>
    <tbody>
      <tr><td>Your own texts &amp; images</td><td>Fully with you as operator</td></tr>
      <tr><td>Embedded external content</td><td>You as embedder — check copyright &amp; licences</td></tr>
      <tr><td>Linked external pages</td><td>No liability for linked content, but link consciously</td></tr>
      <tr><td>Form data &amp; enquiries</td><td>You as recipient — ensure secure transmission &amp; storage</td></tr>
    </tbody>
  </table>
@endsection
@section('related')
  <a href="{{ route('help.en.embed-blocks') }}" class="rel"><span class="mini">Embeds &amp; Forms</span><h4>Embed content correctly</h4><p>5 min · Guide</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.quickstart.user') }}" class="rel"><span class="mini">Getting Started</span><h4>Quick start for users</h4><p>6 min · Step-by-step</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.faq') }}" class="rel"><span class="mini">Q&amp;A</span><h4>FAQ</h4><p>All answers</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
@endsection
