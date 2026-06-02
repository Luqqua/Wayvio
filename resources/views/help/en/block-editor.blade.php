@extends('help.article-layout')
@section('meta_title', 'Block Stack & Editor — all block types & customisation | ' . config('app.name'))
@section('meta_description', 'How the block stack works: add, reorder and customise blocks. Overview of all block types, colours, fonts and media.')
@section('breadcrumb_trail')<span class="here">Block Stack &amp; Editor</span>@endsection
@section<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="4" rx="1"/><rect x="4" y="10" width="16" height="4" rx="1"/><rect x="4" y="16" width="16" height="4" rx="1"/></svg>@endsection
@section('art_title')Block Stack & Editor — choose, stack and customise blocks.@endsection
@section('art_lede')
  {{ config('app.name') }} doesn't use a classic text editor. Instead, you choose pre-built blocks, stack them vertically and customise content, colours and media directly — no code, no drag-and-drop chaos.
@endsection
@section('content')
  <div class="callout tip"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div><div><h4>The principle in one sentence</h4><p>Every page is a vertical stack of blocks. You decide which blocks are included and in what order — no blank canvas, no pixel wrestling.</p></div></div>
  <h2 id="add-block">Adding &amp; reordering blocks</h2>
  <p>In the editor your current stack is on the left and a live preview on the right. To add a block: click <em>Add block</em> below the last element, choose from the block library, then drag it to the desired position. To remove a block, click it and select <em>Delete</em> — undo with Ctrl+Z / Cmd+Z.</p>
  <h2 id="block-types">Overview of all block types</h2>
  <h3>Content &amp; presentation</h3>
  <ul>
    <li><strong>Profile block</strong> — name, short description, profile image. Usually placed at the top.</li>
    <li><strong>Text block</strong> — free-form text, ideal for offer descriptions or "about me".</li>
    <li><strong>Heading block</strong> — visual divider between content sections.</li>
    <li><strong>Image block</strong> — single image with optional caption.</li>
    <li><strong>Gallery block</strong> — multiple images in a grid, clickable as lightbox.</li>
  </ul>
  <h3>Links &amp; CTAs</h3>
  <ul>
    <li><strong>Link block</strong> — single clickable link with icon and description. The classic for link-in-bio pages.</li>
    <li><strong>CTA block</strong> — highlighted call-to-action button with optional subtitle.</li>
    <li><strong>Button group</strong> — two or more buttons side by side.</li>
  </ul>
  <h3>Social &amp; embeds</h3>
  <ul>
    <li><strong>Social links block</strong> — icons for Instagram, LinkedIn, TikTok, YouTube and more.</li>
    <li><strong>Embed block</strong> — any iFrame content: booking calendars, maps, widgets.</li>
    <li><strong>Video block</strong> — YouTube or Vimeo video with automatic consent layer.</li>
  </ul>
  <h3>Trust &amp; reviews</h3>
  <ul>
    <li><strong>Review block</strong> — star rating with quote and name. Manually maintained.</li>
    <li><strong>Logo strip</strong> — client or partner logos in a horizontal row.</li>
    <li><strong>Quote block</strong> — single testimonial displayed prominently.</li>
  </ul>
  <h3>Contact &amp; forms</h3>
  <ul>
    <li><strong>Contact form block</strong> — native form (name, email, message) without third parties. Submissions go directly to your dashboard.</li>
    <li><strong>Address block</strong> — postal address with optional map preview.</li>
    <li><strong>Opening hours block</strong> — structured table for weekdays and times.</li>
  </ul>
  <div class="callout warn"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><div><h4>Less is more</h4><p>Pages with more than 10–12 blocks lose focus and convert worse. Start with the minimum, and only add more once analytics show what your visitors are looking for.</p></div></div>
  <h2 id="images-media">Images &amp; media</h2>
  <p>Upload files directly (JPG, PNG, WebP — max. 10 MB). {{ config('app.name') }} automatically compresses and optimises images for web and mobile. Always add descriptive alt text — it helps screen readers and Google.</p>
  <h2 id="colours-fonts">Colours &amp; fonts per page</h2>
  <p>Under <strong>Settings → Design</strong> you can set the colour scheme and font pairing for the entire page. Changes apply globally across all blocks — individual blocks can usually override globally.</p>
  <table>
    <thead><tr><th>Setting</th><th>Where</th><th>Effect</th></tr></thead>
    <tbody>
      <tr><td>Primary colour</td><td>Design → Colours</td><td>Buttons, links, accents</td></tr>
      <tr><td>Background colour</td><td>Design → Colours</td><td>Page background</td></tr>
      <tr><td>Font pairing</td><td>Design → Typography</td><td>Headings + body text</td></tr>
      <tr><td>Dark mode</td><td>Design → Appearance</td><td>Automatic or forced</td></tr>
    </tbody>
  </table>
@endsection
@section('related')
  <a href="{{ route('help.en.quickstart.user') }}" class="rel"><span class="mini">Getting Started</span><h4>Quick start for users</h4><p>6 min · Step-by-step</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.header-modi') }}" class="rel"><span class="mini">Block Stack</span><h4>Header modes</h4><p>4 min · Guide</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.seo-meta') }}" class="rel"><span class="mini">SEO</span><h4>SEO &amp; meta data</h4><p>5 min · Guide</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
@endsection
