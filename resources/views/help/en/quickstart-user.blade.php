@extends('help.article-layout')

@section('meta_title', 'Quick Start for Users — Hub live in 10 minutes | ' . config('app.name'))
@section('meta_description', 'Step-by-step guide for users: set up account, choose template, build block stack and publish your page.')

@section('breadcrumb_trail')
  <span class="here">Getting Started</span>
@endsection
@section('art_title')Quick Start for Users — from empty template to published page.@endsection
@section('art_lede')
  This guide takes you through account setup, template selection, block stack and going live — in 10 minutes. No code, no technical knowledge required — {{ config('app.name') }} is an all-in-one solution out of the box.
@endsection

@section('content')

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>What is the block stack mechanism?</h4>
      <p>{{ config('app.name') }} doesn't use a classic editor. You choose pre-built blocks (e.g. profile, links, CTA, gallery) and stack them on top of each other. The order is set by drag &amp; drop — that's it.</p>
    </div>
  </div>

  <h2 id="step-1">1. Set up your account</h2>
  <p>Register with your email address and confirm your account via the link sent to you. Next, set your display name and optionally a profile picture — both can be changed at any time.</p>

  <h2 id="step-2">2. Choose a template</h2>
  <p>{{ config('app.name') }} offers pre-built templates for different industries and goals: hospitality, trades, creatives, link-in-bio and more. Choose the template closest to your goal. You can customise or replace all blocks afterwards.</p>

  <div class="figure"><span>Screenshot: Template selection during onboarding</span></div>
  <p class="caption">All templates are fully customisable — they're just a starting point.</p>

  <h2 id="step-3">3. Build your block stack</h2>
  <p>In the editor, your current block stack is on the left and a live preview on the right. Add blocks, reorder them by drag &amp; drop and customise text, colours and images directly.</p>

  <h3>Recommended base structure</h3>
  <ol>
    <li><strong>Profile block</strong> — name, short description, image</li>
    <li><strong>Core offer</strong> — what you provide, in 1–2 sentences</li>
    <li><strong>Primary CTA</strong> — booking, contact form or most important link at the top</li>
    <li><strong>Trust elements</strong> — reviews, references, logos</li>
    <li><strong>Further links</strong> — social media, portfolio, secondary CTAs</li>
  </ol>

  <div class="callout warn">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div>
      <h4>Keep a clear focus</h4>
      <p>Too many blocks dilute the goal of your page. Start with the minimum (profile, CTA, 2–3 links) and only add more when you know what your visitors need.</p>
    </div>
  </div>

  <h2 id="step-4">4. Set SEO &amp; meta data</h2>
  <p>Under <strong>Settings → SEO</strong> you can customise the title tag, meta description and Open Graph image. {{ config('app.name') }} automatically generates a sitemap and sets technical SEO basics — you only need to fill in the editorial part.</p>
  <ul>
    <li><strong>Title</strong> — max. 60 characters, main keyword first</li>
    <li><strong>Meta description</strong> — max. 155 characters, include a call to action</li>
    <li><strong>OG image</strong> — 1200 × 630 px, shown in social media shares</li>
  </ul>

  <h2 id="step-5">5. Check legal requirements</h2>
  <p>{{ config('app.name') }} provides tools for imprint and privacy policy. <strong>Important:</strong> these generators help you prepare — completeness and final review is your responsibility as the operator. If in doubt, consult a lawyer.</p>

  <h2 id="step-6">6. Publish &amp; connect domain</h2>
  <p>Click <em>Publish</em> — your page is immediately accessible via the free Wayvio sub-domain. Optionally, connect your own domain under <strong>Settings → Domains</strong>.</p>

  <h3>Pre-launch checklist</h3>
  <ul>
    <li>Primary CTA is visible without scrolling</li>
    <li>All links open correctly (including on mobile)</li>
    <li>Preview viewed on smartphone</li>
    <li>Meta title and description set</li>
    <li>Imprint and privacy policy linked</li>
  </ul>

@endsection

@section('related')
  <a href="{{ route('help.en.domains') }}" class="rel">
    <span class="mini">Domains &amp; SSL</span>
    <h4>Connect your own domain</h4>
    <p>4 min · Guide</p>
    <span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.en.embed-blocks') }}" class="rel">
    <span class="mini">Embeds &amp; Forms</span>
    <h4>Embed content correctly</h4>
    <p>5 min · Guide</p>
    <span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.en.faq') }}" class="rel">
    <span class="mini">Q&amp;A</span>
    <h4>Frequently asked questions</h4>
    <p>All topics</p>
    <span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
