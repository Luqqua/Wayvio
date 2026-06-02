@extends('help.layout')

@section('main')
@php
  $artLocale = str_starts_with(request()->path(), 'help/en') ? 'en' : 'de';
@endphp
<section class="art-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="{{ $artLocale === 'en' ? 'Breadcrumb' : 'Brotkrumen' }}">
      <a href="{{ $artLocale === 'en' ? route('help.en.index') : route('help.index') }}">{{ $artLocale === 'en' ? 'Help Center' : 'Hilfezentrum' }}</a>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      @yield('breadcrumb_trail')
    </nav>

    <span hidden aria-hidden="true" style="display:none">@yield('art_category_icon')@yield('art_category_label')</span>
    <h1 class="art-title">@yield('art_title')</h1>
    <p class="art-lede">@yield('art_lede')</p>
  </div>
</section>

<div class="container">
  <div class="art-shell">
    <article class="article">
      @yield('content')

      @hasSection('related')
        <section class="related">
          <h3>{{ $artLocale === 'en' ? 'Related Articles' : 'Verwandte Artikel' }}</h3>
          <div class="related-grid">
            @yield('related')
          </div>
        </section>
      @endif
    </article>
  </div>
</div>
@endsection
