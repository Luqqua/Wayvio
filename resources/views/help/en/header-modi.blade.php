@extends('help.article-layout')
@section('meta_title', 'Header Modes — Link in Bio, Business & Business Header | ' . config('app.name'))
@section('meta_description', 'The three header layout modes in Wayvio: Link in Bio, Business (title focus) and Business Header (description focus) — what each mode does and when to use it.')
@section('breadcrumb_trail')<span class="here">Header Modes</span>@endsection
@section<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="6" rx="1"/><path d="M2 13h8M2 17h5"/></svg>@endsection
@section('art_title')Header Modes — logo, title or description in focus.@endsection
@section('art_lede')
  {{ config('app.name') }} offers three layout modes for the header area that control what gets visual priority and where your social icons appear. You can find this setting under <strong>Studio → Header</strong> in the profile content section.
@endsection
@section('content')

  <h2 id="link-in-bio">Link in Bio (Focus logo)</h2>
  <p>The default mode. Your logo is the visual centrepiece of the header. Social media icons appear directly in the header area alongside your name and description.</p>
  <h3>When to use</h3>
  <ul>
    <li>Classic link-in-bio structure where the profile image or logo is the anchor</li>
    <li>Creators, personal brands and social-first pages</li>
    <li>You want social icons prominently at the top, immediately visible</li>
  </ul>

  <h2 id="business-title">Business (Focus title)</h2>
  <p>The title and name take centre stage. The layout is optimised for business presentations where the brand or company name should be immediately readable. Social icons move to the footer. The separate header banner is not available in this layout — the hero image can optionally be enabled on top.</p>
  <h3>When to use</h3>
  <ul>
    <li>Local businesses, service providers and companies with an established name</li>
    <li>You want a clear, professional first impression with the name in focus</li>
    <li>Social links are secondary — footer placement is sufficient</li>
  </ul>

  <h2 id="business-description">Business Header (Focus description)</h2>
  <p>The description is the dominant element — name and logo are present but noticeably smaller and recede into the background. Ideal when a new visitor needs to understand immediately what you offer. Social icons move to the footer. As with Business, the separate header banner is not available; the hero image can optionally be enabled on top.</p>
  <h3>When to use</h3>
  <ul>
    <li>Businesses or freelancers whose audience comes from search or ads and doesn't know them yet</li>
    <li>The description is essential for conversion</li>
    <li>You want explanatory text directly in the header</li>
  </ul>
  <p>This is the <strong>recommended starting layout</strong> for most users without an established brand.</p>

  <h2 id="comparison">Quick comparison</h2>
  <table>
    <thead><tr><th>Mode</th><th>Focus</th><th>Social icons</th></tr></thead>
    <tbody>
      <tr><td>Link in Bio</td><td>Logo</td><td>Header</td></tr>
      <tr><td>Business</td><td>Title / name</td><td>Footer</td></tr>
      <tr><td>Business Header</td><td>Title + description</td><td>Footer</td></tr>
    </tbody>
  </table>

@endsection
@section('related')
  <a href="{{ route('help.en.seitenaufbau') }}" class="rel"><span class="mini">Getting Started</span><h4>Recommended page structure</h4><p>5 min · Guide</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.block-editor') }}" class="rel"><span class="mini">Block Stack</span><h4>All block types</h4><p>6 min · Reference</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
  <a href="{{ route('help.en.quickstart.user') }}" class="rel"><span class="mini">Getting Started</span><h4>Quick start for users</h4><p>6 min · Step-by-step</p><span class="arr">Read <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span></a>
@endsection
