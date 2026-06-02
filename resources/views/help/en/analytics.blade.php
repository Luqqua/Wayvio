@extends('help.article-layout')
@section('meta_title', 'Understanding Analytics — visitors, clicks & conversions | ' . config('app.name'))
@section('meta_description', 'Understand the Wayvio analytics dashboard: visitors, page views, click rates and conversion goals — how to read and optimise with data.')
@section('breadcrumb_trail')<span class="here">Analytics</span>@endsection
@section<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>@endsection
@section('art_title')Understanding Analytics — visitors, clicks and conversions.@endsection
@section('art_lede')
  The analytics dashboard shows how visitors interact with your hub. This guide explains all metrics, shows you how to spot weak points and helps you optimise based on data.
@endsection
@section('content')
  <div class="callout tip"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div><div><h4>No external tracking required</h4><p>{{ config('app.name') }} tracks visitors and clicks in a privacy-compliant, cookie-free way. No Google Analytics or Meta Pixel needed — all relevant data is directly in the dashboard.</p></div></div>
  <h2 id="overview">The analytics dashboard overview</h2>
  <p>Open <strong>Dashboard → Analytics</strong>. At the top you'll see four key indicators for the selected period (default: last 30 days):</p>
  <table>
    <thead><tr><th>Metric</th><th>What it means</th><th>Good when…</th></tr></thead>
    <tbody>
      <tr><td><strong>Visitors</strong></td><td>Unique sessions on your page</td><td>Growing over time</td></tr>
      <tr><td><strong>Page views</strong></td><td>Total number of page loads</td><td>Closer to visitors = fewer bounces</td></tr>
      <tr><td><strong>Clicks</strong></td><td>Total clicks on all blocks and links</td><td>Rises proportionally to visitors</td></tr>
      <tr><td><strong>CTR</strong></td><td>Clicks ÷ visitors (click-through rate)</td><td>≥ 30% is a solid baseline</td></tr>
    </tbody>
  </table>
  <h2 id="click-details">Analysing click data per block</h2>
  <p>The <strong>Clicks</strong> tab shows each block with its click count and click rate. You can immediately see which elements get attention and which are ignored.</p>
  <h3>What to derive from click data</h3>
  <ul>
    <li><strong>Primary CTA has few clicks</strong> → it's too far down or not prominent enough — move it up or make it more visually distinctive.</li>
    <li><strong>Social links get many clicks, CTA gets few</strong> → visitors want to know more about you before acting — add trust elements (reviews, description).</li>
    <li><strong>One link gets almost all clicks</strong> → this is your conversion driver — add more like it or position it higher.</li>
    <li><strong>No block gets clicks</strong> → visitors are bouncing — check load time, mobile display and whether the first visible content is immediately relevant.</li>
  </ul>
  <h2 id="time-periods">Time periods and comparisons</h2>
  <p>Use the date range selector in the top right of the dashboard to compare different periods: this week vs. last week, this month vs. previous month, or campaign launch period vs. before.</p>
  <h2 id="traffic-sources">Understanding traffic sources</h2>
  <ul>
    <li><strong>Direct</strong> — bookmark, typed URL or unattributable (often social media apps)</li>
    <li><strong>Organic</strong> — search engines (Google, Bing)</li>
    <li><strong>Referral</strong> — other websites linking to you</li>
    <li><strong>Social</strong> — clicks from social media links (if UTM parameters are set)</li>
  </ul>
  <div class="callout"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div><div><h4>Use UTM parameters for campaigns</h4><p>Append UTM parameters to links in email newsletters or ads — e.g. <code>?utm_source=instagram&amp;utm_medium=bio</code> — to attribute traffic precisely to a source.</p></div></div>
  <h2 id="optimise">Data-driven optimisation</h2>
  <ol>
    <li><strong>Weekly</strong> — quick check of CTR and top 3 click blocks.</li>
    <li><strong>Monthly</strong> — trend comparison to previous month, test one concrete change.</li>
    <li><strong>Quarterly</strong> — review overall strategy: does the primary conversion goal still match the click data?</li>
  </ol>
@endsection
@section('related')
  <a href="{{ route('help.en.seitenaufbau') }}" class="rel"><span class="mini">Getting Started</span><h4>Recommended page structure</h4><p>5 min · Guide</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.quickstart.user') }}" class="rel"><span class="mini">Getting Started</span><h4>Quick start for users</h4><p>6 min · Step-by-step</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.seo-meta') }}" class="rel"><span class="mini">SEO</span><h4>SEO &amp; meta data</h4><p>5 min · Guide</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
@endsection
