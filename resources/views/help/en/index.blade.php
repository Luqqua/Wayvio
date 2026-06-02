@extends('help.layout')

@section('meta_title', config('app.name') . ' — Help Center')
@section('meta_description', 'Guides, step-by-step instructions and answers — from your first block stack to white-label agency setup.')

@php
  $chevSvg = '<svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
  $arrowSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
@endphp

@section('main')

<section class="hero">
  <div class="container">
    <span class="eyebrow"><span class="dot"></span>Help Center · {{ config('app.name') }}</span>
    <h1 class="hero-title">How can we <em>help?</em></h1>
    <p class="hero-sub">Guides, step-by-step instructions and answers — from your first block stack to white-label agency setup.</p>

  </div>
</section>

<section class="block" style="padding-top:64px;padding-bottom:24px">
  <div class="container">
    <div class="popular-row">
      <div class="section-head" style="margin-bottom:0">
        <div class="section-eyebrow">Popular Articles</div>
        <h2 class="section-title">These guides help most people.</h2>
      </div>
    </div>

    <div class="popular-grid">
      <a href="{{ route('help.en.quickstart.user') }}" class="pop">
        <span class="pop-mini">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Getting Started
        </span>
        <h3>From zero to live in 10 minutes: your first block stack.</h3>
        <p>Choose a template, stack blocks, connect a domain — the fastest way to your first published page.</p>
        <div class="pop-foot">
          <span>6 min · Step-by-step</span>
          <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
      </a>

      <a href="{{ route('help.en.domains') }}" class="pop">
        <span class="pop-mini">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>
          Domains &amp; SSL
        </span>
        <h3>Connect your own domain — configure DNS correctly.</h3>
        <p>A-record, CNAME and automatic SSL: what to enter at your domain provider.</p>
        <div class="pop-foot">
          <span>4 min · Guide</span>
          <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
      </a>

      <a href="{{ route('help.en.embed-blocks') }}" class="pop">
        <span class="pop-mini">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          Embeds &amp; Forms
        </span>
        <h3>Embed content correctly — Instagram, YouTube, Maps and forms.</h3>
        <p>Which embeds are allowed, how to handle consent and protect page performance.</p>
        <div class="pop-foot">
          <span>5 min · Guide</span>
          <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
      </a>

      <a href="{{ route('help.en.analytics') }}" class="pop">
        <span class="pop-mini">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Analytics
        </span>
        <h3>Understand analytics — reading visitors, clicks and conversions.</h3>
        <p>All metrics explained: CTR, traffic sources and how to optimise with data.</p>
        <div class="pop-foot">
          <span>5 min · Guide</span>
          <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
      </a>
    </div>
  </div>
</section>

<section class="block" style="padding-top:48px">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Categories</div>
      <h2 class="section-title">Choose a topic.</h2>
      <p class="section-sub">Sorted by topic — from onboarding to white-label. Each category has its own overview with all related articles.</p>
    </div>

    <div class="cat-grid">

      <article class="cat">
        <div class="cat-head"><div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div></div>
        <h3>Getting Started</h3>
        <p>Set up your account, choose a template, and publish your first block stack.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.en.quickstart.user') }}">Quick start: hub live in 10 minutes {!! $chevSvg !!}</a></li>
          <li><a href="{{ route('help.en.seitenaufbau') }}">Recommended page structure {!! $chevSvg !!}</a></li>
          <li><a href="{{ route('help.en.analytics') }}">Understanding analytics &amp; click data {!! $chevSvg !!}</a></li>
        </ul>
      </article>

      <article class="cat">
        <div class="cat-head"><div class="badge dark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="4" rx="1"/><rect x="4" y="10" width="16" height="4" rx="1"/><rect x="4" y="16" width="16" height="4" rx="1"/></svg></div></div>
        <h3>Block Stack &amp; Editor</h3>
        <p>Choose, stack, reorder and customise blocks — no classic editor, pure stacking.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.en.block-editor') }}">All block types overview {!! $chevSvg !!}</a></li>
          <li><a href="{{ route('help.en.header-modi') }}">Header modes: Title, Business, Minimal &amp; Hero {!! $chevSvg !!}</a></li>
        </ul>
      </article>

      <article class="cat">
        <div class="cat-head"><div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg></div></div>
        <h3>Domains &amp; SSL</h3>
        <p>Connect your own domain, use sub-domains and understand automatic SSL certificates.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.en.domains') }}">Connect domain — SSL is set up automatically {!! $chevSvg !!}</a></li>
        </ul>
      </article>

      <article class="cat">
        <div class="cat-head"><div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg></div></div>
        <h3>Legal &amp; GDPR</h3>
        <p>Imprint, privacy policy and cookie consent — tools for preparation. Final review is your responsibility.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.en.rechtssicherheit') }}">Imprint generator, privacy policy &amp; cookie consent {!! $chevSvg !!}</a></li>
        </ul>
      </article>

      <article class="cat">
        <div class="cat-head"><div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><path d="M11 8v6M8 11h6"/></svg></div></div>
        <h3>SEO &amp; Meta Data</h3>
        <p>Title tags, descriptions, Open Graph images and technical basics for better discoverability.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.en.seo-meta') }}">Title, meta description, Open Graph &amp; sitemap {!! $chevSvg !!}</a></li>
        </ul>
      </article>

      <article class="cat">
        <div class="cat-head"><div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></div></div>
        <h3>Embeds &amp; Forms</h3>
        <p>Instagram, YouTube, Spotify, Maps, Calendly — and native contact forms without third parties.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.en.embed-blocks') }}">Embed content: YouTube, Maps, Instagram &amp; more {!! $chevSvg !!}</a></li>
        </ul>
      </article>

      <article class="cat">
        <div class="cat-head"><div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div></div>
        <h3>Q&amp;A &amp; FAQ</h3>
        <p>Setup, embeds, domains, SEO and agency workflows — direct answers to the most common questions.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.en.faq') }}">All questions &amp; answers {!! $chevSvg !!}</a></li>
        </ul>
      </article>

      <article class="cat">
        <div class="cat-head"><div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M3 21a9 9 0 0118 0"/></svg></div></div>
        <h3>Account &amp; Billing</h3>
        <p>Switch plans, manage payment details, download invoices, cancel account.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.en.account-abrechnung') }}">Plans, invoices, 2FA &amp; cancellation {!! $chevSvg !!}</a></li>
        </ul>
      </article>

      <article class="cat">
        <div class="cat-head"><div class="badge dark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21a6 6 0 0112 0"/><path d="M14 21a5 5 0 017-4.6"/></svg></div></div>
        <h3>Agency &amp; White-Label</h3>
        <p>Manage multiple clients centrally, use your own brand, onboard clients.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.en.quickstart.agency') }}">Agency quick start &amp; client onboarding {!! $chevSvg !!}</a></li>
          <li><a href="{{ route('help.en.white-label') }}">White-label: your own logo &amp; branding {!! $chevSvg !!}</a></li>
        </ul>
      </article>

    </div>
  </div>
</section>

<section class="block" style="padding-top:24px">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Quick Start</div>
      <h2 class="section-title">Two paths to get started.</h2>
      <p class="section-sub">Whether you're publishing a first page or managing multiple client hubs: here's the fastest entry point.</p>
    </div>

    <div class="quick">
      <a href="{{ route('help.en.quickstart.user') }}" class="quick-card">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
        <h3>Quick start for users</h3>
        <p>From an empty template to a published page in 10 minutes: profile, block stack, CTA and links.</p>
        <span class="qcta">Open user guide <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
      </a>
      <a href="{{ route('help.en.quickstart.agency') }}" class="quick-card alt">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21a6 6 0 0112 0"/><path d="M14 21a5 5 0 017-4.6"/></svg></div>
        <h3>Quick start for agencies</h3>
        <p>Set up client hubs, standardise deployment and roll out across teams.</p>
        <span class="qcta">Open agency guide <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
      </a>
    </div>
  </div>
</section>

<section class="block" style="padding-top:24px">
  <div class="container">
    <div class="contact">
      <div>
        <h3>Didn't find what you were looking for?</h3>
        <p>Write to us directly — we usually respond within 24 hours on business days, often much faster. Pro and agency customers have prioritised support.</p>
      </div>
      <div class="contact-actions">
        <a href="{{ route('help.en.faq') }}" class="btn btn-ghost">All Q&amp;A</a>
        <a href="mailto:support@example.com" class="btn btn-primary">Contact support <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
      </div>
    </div>
  </div>
</section>

@endsection
