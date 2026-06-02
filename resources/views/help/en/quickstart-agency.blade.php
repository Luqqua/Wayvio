@extends('help.article-layout')

@section('meta_title', 'Quick Start for Agencies — scalable hub deployment | ' . config('app.name'))
@section('meta_description', 'Agency guide: set up client hubs, standardise workflows, roll out domains and establish QA processes.')

@section('breadcrumb_trail')
  <span class="here">Agency &amp; White-Label</span>
@endsection
@section('art_title')Quick Start for Agencies — scalable hub deployment for multiple clients.@endsection
@section('art_lede')
  This guide shows how to structure client hubs efficiently, standardise deployment and scale your rollout process across teams — with {{ config('app.name') }} as the central hub for all your clients.
@endsection

@section('content')

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Agency account vs. standard account</h4>
      <p>In the agency plan you manage all client hubs centrally in one dashboard. Clients can be invited and only get access to their own hub — you retain full control.</p>
    </div>
  </div>

  <h2 id="phase-1">Phase 1: Account and hub structure</h2>
  <p>Before onboarding the first client, establish consistent standards — this saves time with every subsequent client and reduces errors.</p>
  <ul>
    <li>Define <strong>naming conventions</strong> for clients, hubs and environments (live/staging).</li>
    <li>Assign responsibilities: who is responsible for content, technical setup and approvals?</li>
    <li>Set <strong>one primary conversion goal</strong> per hub — before placing any blocks.</li>
    <li>Create an internal template as the starting point for all new client hubs.</li>
  </ul>

  <h2 id="phase-2">Phase 2: Standardised page baseline</h2>
  <p>A consistent block set as a starting point reduces onboarding time and ensures no hub goes live without essential elements.</p>

  <h3>Recommended standard block set</h3>
  <ol>
    <li>Profile / hero block with client name and core CTA</li>
    <li>Short offer description (1–2 sentences)</li>
    <li>Trust elements (reviews, logos, references)</li>
    <li>Primary CTA (booking, contact, product)</li>
    <li>Secondary links and social media connections</li>
  </ol>

  <h2 id="phase-3">Phase 3: Domain and launch</h2>
  <p>A structured domain rollout prevents downtime and saves support effort — especially when multiple clients go live simultaneously.</p>
  <ul>
    <li>Document DNS changes per client in a consistent format (provider, record type, value, TTL).</li>
    <li>Check SSL status <strong>before</strong> campaign launch.</li>
    <li>Always have a rollback plan ready (e.g. Wayvio sub-domain as fallback).</li>
    <li>Test every hub on mobile and desktop before handoff to client.</li>
  </ul>

  <div class="callout warn">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div>
      <h4>Campaign launches and DNS timing</h4>
      <p>Start DNS changes at least 48 hours before the planned launch. Propagation can vary by provider — never plan a hard deadline without a DNS buffer.</p>
    </div>
  </div>

  <h2 id="phase-4">Phase 4: QA and ongoing operations</h2>
  <h3>QA checklist before every handoff</h3>
  <ul>
    <li>Primary CTA is visible on mobile without scrolling</li>
    <li>All links tested (desktop + smartphone)</li>
    <li>Domain and SSL active (<code>Connected · SSL active</code>)</li>
    <li>Meta title and description set</li>
    <li>Imprint and privacy policy linked and complete</li>
    <li>No unused or broken embeds active</li>
  </ul>

@endsection

@section('related')
  <a href="{{ route('help.en.white-label') }}" class="rel">
    <span class="mini">White-Label</span>
    <h4>Your own logo &amp; branding</h4>
    <p>6 min · Guide</p>
    <span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.en.domains') }}" class="rel">
    <span class="mini">Domains &amp; SSL</span>
    <h4>Connect domain &amp; set up DNS</h4>
    <p>4 min · Guide</p>
    <span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.en.embed-blocks') }}" class="rel">
    <span class="mini">Embeds &amp; Forms</span>
    <h4>Embed content correctly</h4>
    <p>5 min · Guide</p>
    <span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
