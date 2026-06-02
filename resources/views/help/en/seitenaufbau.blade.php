@extends('help.article-layout')
@section('meta_title', 'Recommended Page Structure — optimal block order | ' . config('app.name'))
@section('meta_description', 'Which blocks in which order work best: the recommended page structure for local businesses, creatives, hospitality and link-in-bio pages.')
@section('breadcrumb_trail')<span class="here">Page Structure</span>@endsection
@section<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="4" rx="1"/><rect x="3" y="10" width="11" height="11" rx="1"/><rect x="16" y="10" width="5" height="5" rx="1"/><rect x="16" y="17" width="5" height="4" rx="1"/></svg>@endsection
@section('art_title')Recommended Page Structure — the optimal block order for your hub.@endsection
@section('art_lede')
  The order of your blocks influences whether visitors stay or bounce. This guide shows proven structures for different goals — from local businesses to link-in-bio pages.
@endsection
@section('content')
  <h2 id="universal">The universal base structure</h2>
  <p>This order works for most goals and industries as a solid starting point:</p>
  <ol>
    <li><strong>Hero / profile block</strong> — who you are, what you offer. In one sentence. With profile photo or logo.</li>
    <li><strong>Primary CTA</strong> — the most important action as high up as possible: booking, contact, purchase. No scrolling needed.</li>
    <li><strong>Core offer</strong> — 2–4 specific services or products with brief benefit text.</li>
    <li><strong>Trust elements</strong> — reviews, references, logos, awards. Proof, not claims.</li>
    <li><strong>Secondary links</strong> — social media, portfolio, further pages. No distraction potential at the top, but accessible.</li>
  </ol>
  <div class="callout tip"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div><div><h4>The first visible area is decisive</h4><p>What visitors see without scrolling (above the fold) determines whether they stay. The primary CTA must be visible here — without exception.</p></div></div>
  <h2 id="local-business">Structure for local businesses</h2>
  <p>For hair salons, restaurants, tradespeople, doctors, coaches and similar local services:</p>
  <ol>
    <li>Profile block (name, short description, photo)</li>
    <li>CTA block "Book appointment now" or "Call" — at the very top</li>
    <li>Services block (3–5 services briefly described)</li>
    <li>Review block (Google reviews or manually entered)</li>
    <li>Opening hours block</li>
    <li>Address block with map embed</li>
    <li>Contact form block as fallback</li>
    <li>Social links block at the bottom</li>
  </ol>
  <h2 id="creatives">Structure for creatives &amp; freelancers</h2>
  <p>For photographers, designers, copywriters, musicians and other creatives:</p>
  <ol>
    <li>Profile block (name + tagline describing your USP in 5 words)</li>
    <li>Gallery block or featured project</li>
    <li>CTA "Submit project enquiry" or "View portfolio"</li>
    <li>Quote block (client testimonial)</li>
    <li>Link block to portfolio website or Behance/Dribbble</li>
    <li>Social links</li>
    <li>Contact form</li>
  </ol>
  <h2 id="link-in-bio">Structure for link-in-bio pages</h2>
  <p>For creators, influencers or anyone consolidating their bio links:</p>
  <ol>
    <li>Profile block (large profile photo, name, short bio)</li>
    <li>Most important link as CTA block (e.g. latest YouTube upload, current drop)</li>
    <li>3–5 link blocks sorted by relevance</li>
    <li>Social links block</li>
    <li>Optional newsletter signup block</li>
  </ol>
  <div class="callout warn"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><div><h4>Maximum 8–10 blocks to start</h4><p>Every additional block dilutes attention. Start with the minimum and only add when analytics shows what visitors are looking for.</p></div></div>
@endsection
@section('related')
  <a href="{{ route('help.en.block-editor') }}" class="rel"><span class="mini">Block Stack</span><h4>All block types overview</h4><p>6 min · Reference</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.header-modi') }}" class="rel"><span class="mini">Block Stack</span><h4>Header modes</h4><p>4 min · Guide</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.analytics') }}" class="rel"><span class="mini">Analytics</span><h4>Understanding analytics</h4><p>5 min · Guide</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
@endsection
