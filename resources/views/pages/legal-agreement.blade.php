<!doctype html>
@include('layouts.lang')
@php
  $locale = (string) app()->getLocale();
  $activeLegalLocale = in_array((string) ($legalLocale ?? ''), ['de', 'en'], true)
      ? (string) $legalLocale
      : (str_starts_with(strtolower($locale), 'en') ? 'en' : 'de');
  $footerIsEn = $activeLegalLocale === 'en';
  $loginEnabled = Route::has('login');
  $registerEnabled = Route::has('register') && config('auth.allow_registration') && !config('linkstack.single_user_mode');
  $isImprint = ($agreementType ?? '') === 'imprint';
  $versionLabel = __('Version', [], $activeLegalLocale);
  $hashLabel = __('Content hash (SHA-256)', [], $activeLegalLocale);
  $footerLabels = [
      'agb' => $footerIsEn ? 'Terms' : 'AGB',
      'avv' => $footerIsEn ? 'DPA' : 'AVV',
      'privacy' => $footerIsEn ? 'Privacy' : 'Privatsphäre',
      'imprint' => $footerIsEn ? 'Imprint' : 'Impressum',
      'contact' => $footerIsEn ? 'Contact' : 'Kontakt',
      'help' => $footerIsEn ? 'Help Center' : 'Hilfecenter',
      'license' => $footerIsEn ? 'License' : 'Lizenz',
  ];
  $helpUrl = $footerIsEn
      ? (Route::has('help.en.index') ? route('help.en.index') : '')
      : (Route::has('help.index') ? route('help.index') : '');
  $legalLanguageLabel = __('Legal language', [], $activeLegalLocale);
  $switchUrls = is_array($legalLocaleSwitchUrls ?? null) ? $legalLocaleSwitchUrls : [];
  $switchToDeUrl = (string) ($switchUrls['de'] ?? request()->fullUrlWithQuery(['legal_lang' => 'de']));
  $switchToEnUrl = (string) ($switchUrls['en'] ?? request()->fullUrlWithQuery(['legal_lang' => 'en']));
  $resolvedLinks = is_array($legalLinks ?? null) ? $legalLinks : [];
  $agbUrl = (string) ($resolvedLinks['agb'] ?? route('pagesAgb', ['legal_lang' => $activeLegalLocale]));
  $avvUrl = (string) ($resolvedLinks['avv'] ?? route('pagesAvv', ['legal_lang' => $activeLegalLocale]));
  $privacyUrl = (string) ($resolvedLinks['privacy'] ?? route('pagesPrivacy', ['legal_lang' => $activeLegalLocale]));
  $imprintUrl = (string) ($resolvedLinks['imprint'] ?? (Route::has('pagesImprint') ? route('pagesImprint', ['legal_lang' => $activeLegalLocale]) : ''));
  $contactUrl = $imprintUrl;

  // Parse document text into readable sections
  // Format: blocks separated by blank lines; first short line of a block (no colon-value) = heading
  $docSections = [];
  if (!empty($documentText)) {
      $rawBlocks = preg_split('/\n{2,}/', trim((string) $documentText));
      foreach (($rawBlocks ?: []) as $rawBlock) {
          $block = trim((string) $rawBlock);
          if ($block === '') {
              continue;
          }
          $lines = explode("\n", $block);
          $firstLine = trim((string) ($lines[0] ?? ''));
          $restLines = array_slice($lines, 1);
          $restText = implode("\n", array_map('trim', $restLines));

          $isSingleLine = count($lines) === 1;
          $isShortFirstLine = mb_strlen($firstLine) < 55;
          $firstLineHasColon = str_contains($firstLine, ': ');
          $firstLineEndsPeriod = str_ends_with($firstLine, '.');
          $firstLineIsPlaceholder = str_starts_with($firstLine, '[');
          $looksLikeHeading = $isShortFirstLine && !$firstLineHasColon && !$firstLineEndsPeriod && !$firstLineIsPlaceholder && $firstLine !== '';
          $firstLineIsTable = preg_match('/^\|.+\|/', $firstLine);

          if ($firstLineIsTable) {
              $tableRows = [];
              foreach ($lines as $tLine) {
                  $tTrimmed = trim((string) $tLine);
                  if ($tTrimmed === '') continue;
                  if (preg_match('/^\|[\s\-|]+\|$/', $tTrimmed)) continue;
                  $tableRows[] = array_map('trim', array_slice(explode('|', $tTrimmed), 1, -1));
              }
              $docSections[] = ['type' => 'table', 'heading' => '', 'body' => '', 'rows' => $tableRows];
          } elseif ($isSingleLine && $looksLikeHeading) {
              // Standalone heading
              $docSections[] = ['type' => 'heading', 'heading' => $firstLine, 'body' => ''];
          } elseif (!$isSingleLine && $looksLikeHeading) {
              // Block with embedded heading on the first line
              $docSections[] = ['type' => 'heading_body', 'heading' => $firstLine, 'body' => $restText];
          } else {
              // Pure body text
              $docSections[] = ['type' => 'body', 'heading' => '', 'body' => $block];
          }
      }
  }

  $homeUrl = Route::has('home') ? route('home') : url('/');
@endphp
<html lang="{{ $activeLegalLocale }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ $agreementLabel }} - {{ config('app.name') }}</title>

    @if(file_exists(base_path('assets/wayvio/images/').findFile('favicon')))
      <link rel="icon" type="image/png" href="{{ asset('assets/wayvio/images/'.findFile('favicon')) }}">
    @else
      <link rel="icon" type="image/svg+xml" href="{{ asset('assets/wayvio/images/logo.svg') }}">
    @endif

    <script src="{{ asset('assets/js/detect-dark-mode.js') }}"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
      :root {
        --primary: #0F3D3E;
        --accent: #14B8A6;
        --accent-deep: #0EA192;
        --bg: #F3F7F7;
        --bg-2: #E5EFEF;
        --border: #DCE5E5;
        --ink: #0C2A2A;
        --ink-2: #3A5252;
        --ink-3: #637979;
        --white: #fff;
        --radius: 14px;
        --radius-lg: 22px;
        --shadow-sm: 0 1px 2px rgba(15,61,62,.04), 0 1px 3px rgba(15,61,62,.05);
        --shadow: 0 6px 24px -6px rgba(15,61,62,.10), 0 2px 6px rgba(15,61,62,.05);
        --maxw: 1200px;
      }
      *, *::before, *::after { box-sizing: border-box; }
      html, body { margin: 0; padding: 0; }
      body {
        font-family: 'Geist', 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--ink);
        background: var(--bg);
        -webkit-font-smoothing: antialiased;
        line-height: 1.55;
        font-size: 16px;
      }
      img { max-width: 100%; display: block; }
      a { color: inherit; text-decoration: none; }

      .container { max-width: var(--maxw); margin: 0 auto; padding: 0 24px; }

      /* NAV */
      .la-nav {
        position: sticky; top: 0; z-index: 50;
        backdrop-filter: saturate(140%) blur(14px);
        -webkit-backdrop-filter: saturate(140%) blur(14px);
        background: rgba(243,247,247,.82);
        border-bottom: 1px solid rgba(220,229,229,.7);
      }
      .la-nav-inner {
        display: flex; align-items: center; justify-content: space-between;
        height: 64px; gap: 16px;
      }
      .la-logo {
        display: flex; align-items: center; gap: 10px;
        font-weight: 700; font-size: 18px; letter-spacing: -.01em;
        color: var(--ink);
      }
      .la-nav-right { display: flex; align-items: center; gap: 10px; }
      .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        padding: 9px 16px; border-radius: 10px; font-weight: 600; font-size: 14px;
        font-family: inherit; cursor: pointer; border: 0; background: none;
        transition: transform .12s, background .15s, box-shadow .15s;
        white-space: nowrap; line-height: 1;
      }
      .btn-ghost { color: var(--ink); border: 1px solid var(--border); background: var(--white); }
      .btn-ghost:hover { background: var(--bg); }
      .btn-primary { background: var(--primary); color: #fff; box-shadow: var(--shadow-sm); }
      .btn-primary:hover { background: #0a2c2d; transform: translateY(-1px); }
      .lang-toggle {
        display: inline-flex; align-items: center;
        border-radius: 8px; padding: 3px; gap: 2px;
        border: 1px solid var(--border); background: var(--bg);
        font-size: 13px; font-weight: 600;
      }
      .lang-toggle a, .lang-toggle span {
        padding: 4px 10px; line-height: 1; border-radius: 5px;
        color: var(--ink-3); transition: background .12s, color .12s; display: inline-block;
      }
      .lang-toggle a:hover { background: var(--white); color: var(--ink); }
      .lang-toggle .active-lang { background: var(--primary); color: #fff; cursor: default; }

      /* HERO */
      .la-hero {
        position: relative; overflow: hidden;
        padding: 56px 0 48px;
        background:
          radial-gradient(900px 360px at 90% -10%, rgba(20,184,166,.16), transparent 60%),
          radial-gradient(700px 360px at -10% 30%, rgba(15,61,62,.07), transparent 60%),
          linear-gradient(180deg, var(--bg) 0%, #EAF2F2 100%);
      }
      .la-eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 5px 12px 5px 8px; border-radius: 999px;
        background: rgba(20,184,166,.12); color: #0a655a;
        font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
        border: 1px solid rgba(20,184,166,.25); margin-bottom: 18px;
      }
      .la-eyebrow .dot {
        width: 7px; height: 7px; border-radius: 999px;
        background: var(--accent); box-shadow: 0 0 0 3px rgba(20,184,166,.25);
      }
      .la-title {
        font-size: clamp(30px, 3.8vw, 46px);
        line-height: 1.06; letter-spacing: -.025em; font-weight: 700;
        margin: 0 0 12px; color: var(--primary);
      }
      .la-meta {
        display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
        margin-top: 4px;
      }
      .la-meta-chip {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 13px; color: var(--ink-3); font-weight: 500;
        background: rgba(15,61,62,.06); border: 1px solid var(--border);
        border-radius: 8px; padding: 4px 10px;
      }
      .la-meta-chip code {
        font-family: 'Geist Mono', monospace;
        font-size: 12px; color: var(--primary); font-weight: 500;
      }

      /* CONTENT AREA */
      .la-body { padding: 40px 0 80px; }
      .la-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
        max-width: 840px;
        margin: 0 auto;
      }

      /* ARTICLE CARD */
      .la-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 40px 48px;
        box-shadow: var(--shadow-sm);
      }
      .la-card-title {
        font-size: 13px; font-weight: 700; letter-spacing: .08em;
        text-transform: uppercase; color: var(--accent-deep);
        margin: 0 0 20px;
      }

      /* Document sections */
      .la-section + .la-section { margin-top: 28px; }
      .la-section-heading {
        font-size: 17px; font-weight: 700; letter-spacing: -.01em;
        color: var(--primary); margin: 0 0 8px; line-height: 1.25;
      }
      .la-section-body {
        font-size: 15px; color: var(--ink-2); line-height: 1.7;
        margin: 0; white-space: pre-line; word-break: break-word;
      }
      .la-section-body a {
        color: var(--accent-deep); text-decoration: underline;
        text-decoration-color: rgba(14,161,146,.3);
        text-underline-offset: .18em;
      }
      .la-section-body a:hover { text-decoration-color: var(--accent-deep); }

      /* Table */
      .la-table-wrap {
        overflow-x: auto;
        border-radius: 10px;
        border: 1px solid var(--border);
        margin: 0;
      }
      .la-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        color: var(--ink-2);
        line-height: 1.5;
      }
      .la-table th, .la-table td {
        padding: 10px 14px;
        text-align: left;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
      }
      .la-table th {
        background: var(--bg);
        font-weight: 600;
        color: var(--ink);
        white-space: nowrap;
        font-size: 13px;
      }
      .la-table tbody tr:last-child td { border-bottom: 0; }
      .la-table tbody tr:hover { background: var(--bg); }

      /* Hash chip */
      .la-hash {
        margin-top: 28px;
        padding: 14px 16px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        font-size: 12px; color: var(--ink-3);
        display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap;
      }
      .la-hash-label { font-weight: 600; color: var(--ink-2); white-space: nowrap; }
      .la-hash code {
        font-family: 'Geist Mono', monospace;
        font-size: 11px; color: var(--ink-3);
        word-break: break-all; flex: 1;
      }

      /* CONTACT FORM CARD */
      .la-contact-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 40px 48px;
        box-shadow: var(--shadow-sm);
        position: relative; overflow: hidden;
      }
      .la-contact-card::before {
        content: "";
        position: absolute; top: 0; right: 0;
        width: 280px; height: 280px;
        background: radial-gradient(circle at 100% 0%, rgba(20,184,166,.12), transparent 70%);
        pointer-events: none;
      }
      .la-contact-eyebrow {
        display: inline-flex; align-items: center; gap: 7px;
        font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
        color: var(--accent-deep); margin-bottom: 10px;
      }
      .la-contact-eyebrow svg { width: 14px; height: 14px; }
      .la-contact-title {
        font-size: 24px; font-weight: 700; letter-spacing: -.015em;
        color: var(--primary); margin: 0 0 8px;
      }
      .la-contact-sub {
        font-size: 15px; color: var(--ink-2); margin: 0 0 28px; max-width: 480px;
      }

      /* Override form embed styles in contact card */
      .la-contact-card .wayvio-form-embed {
        width: 100%;
        margin: 0; padding-top: 0; border-top: 0;
      }
      .la-contact-card .wayvio-form-title { display: none; }
      .la-contact-card .wayvio-form-row label,
      .la-contact-card .wayvio-form-consent {
        font-size: 14px; color: var(--ink-2); font-weight: 600;
      }
      .la-contact-card .wayvio-form-row input,
      .la-contact-card .wayvio-form-row textarea {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 11px 14px;
        background: var(--bg);
        color: var(--ink);
        font-family: 'Geist', inherit;
        font-size: 15px;
        transition: border-color .18s, box-shadow .18s;
      }
      .la-contact-card .wayvio-form-row input:focus,
      .la-contact-card .wayvio-form-row textarea:focus {
        outline: none;
        border-color: var(--accent-deep);
        box-shadow: 0 0 0 3px rgba(14,161,146,.14);
        background: var(--white);
      }
      .la-contact-card .wayvio-form-row input::placeholder,
      .la-contact-card .wayvio-form-row textarea::placeholder { color: var(--ink-3); }
      .la-contact-card .wayvio-form-consent { color: var(--ink-3); font-weight: 500; }
      .la-contact-card .wayvio-form-consent a { color: var(--accent-deep); }
      .la-contact-card .wayvio-form-submit {
        width: 100%;
        background: var(--primary);
        color: #fff;
        border-radius: 10px;
        padding: 12px 18px;
        font-size: 15px; font-weight: 700;
        min-height: 46px;
        border: 0;
        box-shadow: 0 4px 14px rgba(15,61,62,.2);
        transition: background .15s, transform .12s, box-shadow .15s;
      }
      .la-contact-card .wayvio-form-submit:hover {
        background: #0a2c2d;
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(15,61,62,.28);
      }
      .la-contact-card .wayvio-form-alert-success {
        background: rgba(20,184,166,.1);
        color: #0a655a;
        border-radius: 10px;
        border: 1px solid rgba(20,184,166,.25);
      }
      .la-contact-card .wayvio-form-alert-error {
        background: #fff1e8;
        color: #8a2f0a;
        border-radius: 10px;
      }

      /* FOOTER */
      .la-footer {
        background: var(--bg);
        border-top: 1px solid var(--border);
        padding: 40px 0 28px;
        color: var(--ink-3); font-size: 14px;
      }
      .la-footer-inner {
        display: flex; justify-content: space-between; gap: 24px;
        flex-wrap: wrap; align-items: center;
      }
      .la-footer-links { display: flex; gap: 18px; flex-wrap: wrap; }
      .la-footer-links a:hover { color: var(--ink); }

      /* Responsive */
      @media (max-width: 720px) {
        .la-hero { padding: 40px 0 36px; }
        .la-card, .la-contact-card { padding: 24px 20px; }
        .la-title { font-size: 26px; }
        .la-nav-right .btn-ghost { display: none; }
      }
      @media (max-width: 480px) {
        .la-contact-card::before { display: none; }
      }
    </style>
  </head>

  <body>
    <nav class="la-nav">
      <div class="container la-nav-inner">
        <a href="{{ $homeUrl }}" class="la-logo">
          @if(file_exists(base_path('assets/wayvio/images/').findFile('avatar')))
            <img src="{{ asset('assets/wayvio/images/'.findFile('avatar')) }}" style="width:auto;height:28px;" alt="{{ config('app.name') }}">
          @elseif(file_exists(base_path('assets/wayvio/images/logo_color.svg')))
            <img src="{{ asset('assets/wayvio/images/logo_color.svg') }}" width="26" height="26" alt="{{ config('app.name') }}">
          @else
            <img src="{{ asset('assets/wayvio/images/logo.svg') }}" width="26" height="26" alt="{{ config('app.name') }}">
          @endif
          <span>{{ strtoupper((string) config('app.name')) }}</span>
        </a>

        <div class="la-nav-right">
          <div class="lang-toggle" aria-label="{{ $legalLanguageLabel }}">
            @if($activeLegalLocale === 'en')
              <a href="{{ $switchToDeUrl }}">DE</a>
              <span class="active-lang">EN</span>
            @else
              <span class="active-lang">DE</span>
              <a href="{{ $switchToEnUrl }}">EN</a>
            @endif
          </div>
          @if($loginEnabled)
            @auth
              <a href="{{ url('dashboard') }}" class="btn btn-primary">Dashboard</a>
            @else
              <a href="{{ route('login') }}" class="btn btn-ghost">{{ $footerIsEn ? 'Login' : 'Anmelden' }}</a>
              @if($registerEnabled)
                <a href="{{ route('register') }}" class="btn btn-primary">{{ $footerIsEn ? 'Sign up' : 'Registrieren' }}</a>
              @else
                <a href="{{ url('dashboard') }}" class="btn btn-primary">Dashboard</a>
              @endif
            @endauth
          @endif
        </div>
      </div>
    </nav>

    <div class="la-hero">
      <div class="container">
        <div class="la-eyebrow">
          <span class="dot"></span>
          {{ $footerIsEn ? 'Legal' : 'Rechtliches' }}
        </div>
        <h1 class="la-title">{{ $agreementLabel }}</h1>
        <div class="la-meta">
          <span class="la-meta-chip">
            {{ $versionLabel }}: <code>{{ $agreementVersion }}</code>
          </span>
          @if(!empty($documentHash))
            <span class="la-meta-chip">
              SHA-256: <code>{{ substr($documentHash, 0, 16) }}&hellip;</code>
            </span>
          @endif
        </div>
      </div>
    </div>

    <main class="la-body">
      <div class="container">
        <div class="la-grid">

          {{-- Document content card --}}
          <article class="la-card">
            <p class="la-card-title">{{ $footerIsEn ? 'Legal Notice' : 'Pflichtangaben' }}</p>

            @if(!empty($docSections))
              @php $isFirstBlock = true; @endphp
              @foreach($docSections as $section)
                @php
                  // Skip the leading title block that duplicates the hero heading
                  $skipBlock = $isFirstBlock
                      && in_array($section['type'], ['heading', 'heading_body'], true)
                      && mb_strtolower(trim($section['heading'])) === mb_strtolower(trim($agreementLabel));
                  $isFirstBlock = false;
                @endphp
                @if(!$skipBlock)
                <div class="la-section">
                  @if($section['type'] === 'table')
                    <div class="la-table-wrap">
                      <table class="la-table">
                        <thead>
                          <tr>@foreach($section['rows'][0] as $cell)<th>{{ $cell }}</th>@endforeach</tr>
                        </thead>
                        <tbody>
                          @foreach(array_slice($section['rows'], 1) as $row)
                            <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  @elseif($section['type'] === 'heading')
                    <h2 class="la-section-heading">{{ $section['heading'] }}</h2>
                  @elseif($section['type'] === 'heading_body')
                    <h2 class="la-section-heading">{{ $section['heading'] }}</h2>
                    @php
                      $bodyText = (string) $section['body'];
                      $bodyText = preg_replace(
                          '/(https?:\/\/[^\s]+)/i',
                          '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
                          e($bodyText)
                      ) ?? e($bodyText);
                    @endphp
                    <p class="la-section-body">{!! $bodyText !!}</p>
                  @else
                    @php
                      $bodyText = (string) $section['body'];
                      $bodyText = preg_replace(
                          '/(https?:\/\/[^\s]+)/i',
                          '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
                          e($bodyText)
                      ) ?? e($bodyText);
                    @endphp
                    <p class="la-section-body">{!! $bodyText !!}</p>
                  @endif
                </div>
                @endif
              @endforeach
            @elseif(!empty($documentText))
              <p class="la-section-body">{{ $documentText }}</p>
            @else
              @foreach(($sections ?? []) as $section)
                <div class="la-section">
                  <h2 class="la-section-heading">{{ $section['title'] }}</h2>
                  <p class="la-section-body">{{ $section['body'] }}</p>
                </div>
              @endforeach
            @endif

            @if(!empty($documentHash))
              <div class="la-hash">
                <span class="la-hash-label">{{ $hashLabel }}:</span>
                <code>{{ $documentHash }}</code>
              </div>
            @endif
          </article>

          {{-- Contact form (imprint only) --}}
          @if($isImprint && isset($platformFormsHub) && $platformFormsHub instanceof \App\Models\User)
            <div class="la-contact-card">
              <div class="la-contact-eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                {{ $footerIsEn ? 'Get in touch' : 'Kontakt aufnehmen' }}
              </div>
              <h2 class="la-contact-title">{{ $footerIsEn ? 'Contact us' : 'Kontaktieren Sie uns' }}</h2>
              <p class="la-contact-sub">
                {{ $footerIsEn
                  ? 'Questions or concerns? We\'ll get back to you as quickly as possible.'
                  : 'Haben Sie Fragen oder Anliegen? Wir melden uns schnellstmöglich bei Ihnen.' }}
              </p>
              <x-forms.embed form-key="imprint_contact" :hub="$platformFormsHub" context="imprint" />
            </div>
          @endif

        </div>
      </div>
    </main>

    <footer class="la-footer">
      <div class="container la-footer-inner">
        <a href="{{ $homeUrl }}" class="la-logo" style="font-size:15px">
          @if(file_exists(base_path('assets/wayvio/images/logo_color.svg')))
            <img src="{{ asset('assets/wayvio/images/logo_color.svg') }}" width="20" height="20" alt="{{ config('app.name') }}" aria-hidden="true">
          @else
            <img src="{{ asset('assets/wayvio/images/logo.svg') }}" width="20" height="20" alt="{{ config('app.name') }}" aria-hidden="true">
          @endif
          {{ strtoupper((string) config('app.name')) }}
        </a>
        <div class="la-footer-links">
          <a href="{{ $agbUrl }}">{{ $footerLabels['agb'] }}</a>
          <a href="{{ $avvUrl }}">{{ $footerLabels['avv'] }}</a>
          <a href="{{ $privacyUrl }}">{{ $footerLabels['privacy'] }}</a>
          @if($imprintUrl !== '')<a href="{{ $imprintUrl }}">{{ $footerLabels['imprint'] }}</a>@endif
          @if($contactUrl !== '')<a href="{{ $contactUrl }}">{{ $footerLabels['contact'] }}</a>@endif
          @if($helpUrl !== '')<a href="{{ $helpUrl }}">{{ $footerLabels['help'] }}</a>@endif
          <a href="https://github.com/Luqqua/wayvio" target="_blank" rel="noreferrer">{{ $footerLabels['license'] }}</a>
        </div>
        <div>
          &copy; {{ date('Y') }} {{ strtoupper((string) config('app.name')) }}
        </div>
      </div>
    </footer>

    <script src="{{ asset('assets/js/core/libs.min.js') }}"></script>
    <script src="{{ asset('assets/js/hope-ui.js') }}" defer></script>
  </body>
</html>
