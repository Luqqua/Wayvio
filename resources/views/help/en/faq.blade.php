@extends('help.article-layout')

@section('meta_title', 'Q&A — Frequently Asked Questions | ' . config('app.name'))
@section('meta_description', 'Answers to the most common questions about setup, embed blocks, domains, SEO and agency workflows.')

@php
  $faqGroups = [
    ['title' => 'Setup and structure', 'items' => [
      ['q' => 'How do I start a hub without missing important steps?', 'a' => 'Start with the quick-start guide for your role. Define a primary conversion goal (e.g. booking or contact) — this guides all further decisions. Build the core blocks first (intro, offer, trust, CTA) and add optional elements in a second pass.'],
      ['q' => 'Which block order is recommended?', 'a' => 'A proven structure: Hero/intro, core offer, trust elements, primary CTA, then secondary details and links. This order helps both visitors and search engines quickly understand the page\'s focus.'],
      ['q' => 'How do I make the best use of pre-built templates?', 'a' => 'Choose a template closest to your industry or goal. Then replace placeholder content with your own texts, images and links. The block stack mechanism lets you reorder or remove blocks as needed.'],
    ]],
    ['title' => 'Embed blocks', 'items' => [
      ['q' => 'When should I use an embed block?', 'a' => 'Use embeds when a tool can only be integrated via iFrame or external script (e.g. booking widgets, maps or third-party forms). Native blocks are generally faster for simple content.'],
      ['q' => 'How do I prevent embeds from affecting performance?', 'a' => 'Keep only essential embeds active. Place heavier modules lower on the page. Check mobile performance after every change. Too many simultaneous embeds can significantly delay First Contentful Paint.'],
      ['q' => 'Do embeds need consent handling?', 'a' => 'Often yes. Some providers set cookies or load tracking scripts. Check each provider and align your consent setup accordingly. ' . config('app.name') . ' provides tools — completeness and final review is your responsibility as operator.'],
    ]],
    ['title' => 'Domains and SSL', 'items' => [
      ['q' => 'Which DNS records do I need for a custom domain?', 'a' => 'For the root domain (<code>yourdomain.com</code>) you need an A record pointing to <code>76.76.21.21</code>. For <code>www.yourdomain.com</code>, a CNAME to <code>cname.wayvio.app</code>. Details and provider-specific notes are in the domains guide.'],
      ['q' => 'Why does SSL stay on Pending after the DNS update?', 'a' => 'DNS propagation may still be in progress. Wait until the TTL expires and check that only the required records are active. Duplicate or conflicting A records are the most common cause of issues.'],
      ['q' => 'Can I use a sub-domain instead of the root domain?', 'a' => 'Yes. Sub-domains (e.g. <code>hub.yourdomain.com</code>) are connected via a CNAME record. SSL is automatically included. The workflow is identical to root domain connection.'],
    ]],
    ['title' => 'SEO and visibility', 'items' => [
      ['q' => 'How do I optimise my page for search engines?', 'a' => 'Set a meaningful title tag (max. 60 chars) and meta description (max. 155 chars). Use clear H1/H2 structure in your block content. Add an Open Graph image for social media previews. ' . config('app.name') . ' handles all technical SEO basics out of the box.'],
      ['q' => 'Will my page be automatically indexed by Google?', 'a' => 'Once your page is published and a domain is connected, it\'s accessible to search engines. You can activate a sitemap and customise robots.txt in settings to control indexing.'],
    ]],
    ['title' => 'Agency workflows', 'items' => [
      ['q' => 'How do I scale hub deployment for multiple clients?', 'a' => 'Use standardised templates, consistent naming conventions and a QA checklist for every handoff. A consistent rollout process minimises domain, content and SEO errors.'],
      ['q' => 'Which KPIs should I track for client hubs?', 'a' => 'Proven metrics: time-to-live per hub, domain/SSL error rate, conversion trend per hub and lead time from briefing to launch. Live analytics are available directly in the ' . config('app.name') . ' dashboard.'],
    ]],
  ];
  $faqSchemaItems = [];
  foreach ($faqGroups as $group) {
    foreach ($group['items'] as $item) {
      $faqSchemaItems[] = ['@type' => 'Question', 'name' => strip_tags($item['q']), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($item['a'])]];
    }
  }
@endphp

@push('help-head')
  <script type="application/ld+json">
    {!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqSchemaItems], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
  </script>
@endpush

@section('breadcrumb_trail')
  <span class="here">Q&amp;A</span>
@endsection
@section('art_title')Frequently Asked Questions@endsection
@section('art_lede')
  This FAQ covers setup, embed blocks, domains, SEO and agency workflows — with direct, practical answers.
@endsection

@section('content')
  @foreach($faqGroups as $group)
    <h2>{{ $group['title'] }}</h2>
    @foreach($group['items'] as $item)
      <h3>{{ $item['q'] }}</h3>
      <p>{!! $item['a'] !!}</p>
    @endforeach
  @endforeach
@endsection

@section('related')
  <a href="{{ route('help.en.quickstart.user') }}" class="rel">
    <span class="mini">Getting Started</span>
    <h4>Quick start for users</h4>
    <p>6 min · Step-by-step</p>
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
