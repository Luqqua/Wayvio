@extends('help.article-layout')
@section('meta_title', 'SEO & Meta Data — Title, Description, Open Graph & Sitemap | ' . config('app.name'))
@section('meta_description', 'Set title tags, meta descriptions and Open Graph images correctly. Sitemap, robots.txt and structured data for better Google visibility.')
@section('breadcrumb_trail')<span class="here">SEO &amp; Meta Data</span>@endsection
@section<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><path d="M11 8v6M8 11h6"/></svg>@endsection
@section('art_title')SEO & Meta Data — title, description, Open Graph and sitemap.@endsection
@section('art_lede')
  {{ config('app.name') }} handles all technical SEO basics automatically. This guide shows you how to fill in the title tag, meta description and Open Graph image optimally — the biggest levers for better discoverability in search engines and social media.
@endsection
@section('content')
  <div class="callout tip"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div><div><h4>What {{ config('app.name') }} handles automatically</h4><p>Clean HTML markup, canonical URLs, automatic sitemap, HTTPS, fast load times and correct robots.txt — all active without configuration. You only need to fill in the editorial part.</p></div></div>
  <h2 id="title-meta">Customising title &amp; meta description</h2>
  <p>Open <strong>Settings → SEO</strong>. There you'll find two fields that determine the most important aspects of your Google search result snippet:</p>
  <h3>Title tag</h3>
  <p>The title tag appears as the clickable blue headline in search results and in the browser tab. Keep it under <strong>60 characters</strong> and put the most important keyword as early as possible.</p>
  <ul>
    <li><strong>Good:</strong> <code>Photographer Berlin — Weddings &amp; Portraits · Jane Smith</code></li>
    <li><strong>Less good:</strong> <code>Welcome to my homepage — Jane Smith Photography Berlin</code></li>
  </ul>
  <h3>Meta description</h3>
  <p>The meta description appears as the grey descriptive text below the title in search results. Stay under <strong>155 characters</strong> and include a clear call to action.</p>
  <table>
    <thead><tr><th>Field</th><th>Recommended length</th><th>Effect</th></tr></thead>
    <tbody>
      <tr><td>Title tag</td><td>max. 60 chars</td><td>Ranking + click rate</td></tr>
      <tr><td>Meta description</td><td>max. 155 chars</td><td>Click-through rate (CTR)</td></tr>
      <tr><td>OG title (social)</td><td>max. 65 chars</td><td>Social media preview</td></tr>
    </tbody>
  </table>
  <h2 id="open-graph">Open Graph preview for social media</h2>
  <p>When someone shares your link on LinkedIn, WhatsApp or Twitter/X, a preview card appears automatically with image, title and description. The Open Graph image largely determines whether someone clicks.</p>
  <p>Upload your own OG image under <strong>Settings → SEO → Social preview</strong>. Recommended dimensions: <strong>1200 × 630 px</strong>.</p>
  <h2 id="sitemap-robots">Sitemap, robots.txt &amp; indexing</h2>
  <p>{{ config('app.name') }} automatically generates an XML sitemap at <code>/sitemap.xml</code> and a robots.txt at <code>/robots.txt</code>. To exclude a page from indexing, enable <em>Exclude from search engines</em> under <strong>Settings → SEO → Indexing</strong>.</p>
  <h2 id="structured-data">Structured data for local businesses</h2>
  <p>{{ config('app.name') }} automatically detects which blocks you use and generates appropriate Schema.org markup:</p>
  <ul>
    <li><strong>Address block</strong> → <code>LocalBusiness</code> with address and contact</li>
    <li><strong>Opening hours block</strong> → <code>openingHoursSpecification</code></li>
    <li><strong>Review block</strong> → <code>Review</code> / <code>AggregateRating</code></li>
  </ul>
  <div class="callout warn"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><div><h4>SEO takes time</h4><p>Google typically indexes new pages within days to weeks. Visible ranking improvements develop over weeks and months — not overnight. Consistent content and a fast, technically clean page are the best foundation.</p></div></div>
@endsection
@section('related')
  <a href="{{ route('help.en.quickstart.user') }}" class="rel"><span class="mini">Getting Started</span><h4>Quick start for users</h4><p>6 min · Step-by-step</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.rechtssicherheit') }}" class="rel"><span class="mini">Legal &amp; GDPR</span><h4>Imprint, privacy &amp; consent</h4><p>6 min · Guide</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.domains') }}" class="rel"><span class="mini">Domains &amp; SSL</span><h4>Connect your own domain</h4><p>4 min · Guide</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
@endsection
