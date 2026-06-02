@extends('help.article-layout')

@section('meta_title', 'SEO & Meta-Daten — Seitentitel, Beschreibung, Open Graph & Favicon | ' . config('app.name'))
@section('meta_description', 'Seitentitel, Meta-Beschreibung, Open-Graph-Felder, Favicon und Robots-Einstellung richtig befüllen — alle Felder unter Studio → Meta-Tag erklärt.')

@php
  $articleSchema = [
    '@context' => 'https://schema.org',
    '@type'    => 'Article',
    'headline' => 'SEO & Meta-Daten — Seitentitel, Beschreibung, Open Graph & Favicon',
    'description' => 'Seitentitel, Meta-Beschreibung, Open-Graph-Felder, Favicon und Robots-Einstellung richtig befüllen — alle Felder unter Studio → Meta-Tag erklärt.',
    'author' => ['@type' => 'Organization', 'name' => config('app.name')],
  ];
@endphp

@push('help-head')
  <script type="application/ld+json">
    {!! json_encode($articleSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
  </script>
@endpush

@section('breadcrumb_trail')
  <span class="here">SEO &amp; Meta-Daten</span>
@endsection



@section('art_title')SEO & Meta-Daten — Seitentitel, Beschreibung, Open Graph und Favicon einrichten.@endsection

@section('art_lede')
  {{ config('app.name') }} übernimmt alle technischen SEO-Grundlagen automatisch und setzt seo-optimierte, auf deinen Account abgestimmte Standardwerte — auch wenn du nichts einstellst. Die Felder unter <strong>Studio → Meta-Tag</strong> sind eine erweiterte Einstellung ab Pro, gedacht für individuelle Anpassungen: Seitentitel, Beschreibung, Open-Graph-Felder, Favicon und Crawling-Einstellungen.
@endsection

@section('content')

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Was {{ config('app.name') }} automatisch erledigt</h4>
      <p>Sauberes HTML-Markup, kanonische URLs, HTTPS und schnelle Ladezeiten sind ohne Konfiguration aktiv. Alle Felder sind optional — leer gelassene Felder fallen auf seo-optimierte Standardwerte zurück.</p>
    </div>
  </div>

  <h2 id="seo-basics">SEO basics — Seitentitel, Beschreibung &amp; Keywords</h2>
  <p>Diese drei Felder steuern, wie deine Seite in Suchmaschinen erscheint.</p>

  <h3>Seitentitel</h3>
  <p>Der Seitentitel erscheint als klickbare Überschrift in den Suchergebnissen und im Browser-Tab. Halte ihn unter <strong>60 Zeichen</strong> und stelle das wichtigste Keyword möglichst weit nach vorne.</p>
  <ul>
    <li><strong>Gut:</strong> <code>Fotograf Berlin — Hochzeits- &amp; Portraitfotografie · Max Muster</code></li>
    <li><strong>Weniger gut:</strong> <code>Willkommen auf meiner Homepage — Max Muster Fotografie Berlin</code></li>
  </ul>

  <h3>Beschreibung</h3>
  <p>Die Beschreibung erscheint als grauer Text unter dem Titel in den Suchergebnissen. Sie beeinflusst das Ranking kaum direkt, bestimmt aber die Klickrate. Bleib unter <strong>155 Zeichen</strong> und baue einen klaren Handlungsaufruf ein.</p>

  <h3>SEO-Keywords</h3>
  <p>Kommagetrennte Stichwörter, die den thematischen Kontext deiner Seite beschreiben. Moderne Suchmaschinen nutzen dieses Feld nur noch eingeschränkt — investiere lieber Zeit in Titel und Beschreibung.</p>

  <h2 id="social-sharing">Social sharing — Open Graph &amp; Twitter-Karte</h2>
  <p>Wenn jemand deinen Link auf Social Media teilt, werden diese Felder für die Vorschaukarte verwendet.</p>

  <table>
    <thead>
      <tr><th>Feld</th><th>Wirkung</th></tr>
    </thead>
    <tbody>
      <tr><td>Open-Graph-Titel</td><td>Überschrift der Link-Vorschau auf Social Media</td></tr>
      <tr><td>Open-Graph-Beschreibung</td><td>Beschreibungstext der Link-Vorschau</td></tr>
      <tr><td>Twitter-Kartentyp</td><td>Darstellung auf X / Twitter (summary oder summary_large_image)</td></tr>
      <tr><td>OG-Locale</td><td>Sprache der Seite für Social Platforms (z.&nbsp;B. <code>de_DE</code>)</td></tr>
    </tbody>
  </table>

  <h2 id="favicon">Favicon hochladen</h2>
  <p>Das Favicon erscheint im Browser-Tab und in Lesezeichen. Lade unter <strong>Studio → Meta-Tag → Favicon</strong> eine Bilddatei hoch.</p>
  <ul>
    <li>Format: PNG, JPG oder WebP</li>
    <li>Maximale Größe: 256 × 256 px</li>
  </ul>

  <h2 id="crawling">Crawling &amp; Kanonische URL</h2>

  <h3>Robots</h3>
  <p>Über das Robots-Dropdown steuerst du, ob Suchmaschinen deine Seite indexieren und Links verfolgen dürfen.</p>
  <table>
    <thead>
      <tr><th>Einstellung</th><th>Bedeutung</th></tr>
    </thead>
    <tbody>
      <tr><td>index, follow</td><td>Seite indexieren, Links verfolgen (Standard)</td></tr>
      <tr><td>noindex, follow</td><td>Nicht indexieren, Links trotzdem verfolgen</td></tr>
      <tr><td>index, nofollow</td><td>Indexieren, aber Links nicht verfolgen</td></tr>
      <tr><td>noindex, nofollow</td><td>Weder indexieren noch Links verfolgen</td></tr>
    </tbody>
  </table>

  <h3>Kanonische URL</h3>
  <p>Wenn deine Seite unter mehreren URLs erreichbar ist, gibst du hier die bevorzugte URL an. Das verhindert doppelten Content aus Sicht der Suchmaschinen.</p>

  <div class="callout warn">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div>
      <h4>SEO braucht Zeit</h4>
      <p>Google indexiert neue Seiten üblicherweise innerhalb weniger Tage bis Wochen. Sichtbare Ranking-Verbesserungen entstehen über Wochen und Monate — nicht über Nacht.</p>
    </div>
  </div>

@endsection

@section('related')
  <a href="{{ route('help.quickstart.user') }}" class="rel">
    <span class="mini">Erste Schritte</span>
    <h4>Schnellstart für Nutzer</h4>
    <p>6 Min · Schritt-für-Schritt</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.rechtssicherheit') }}" class="rel">
    <span class="mini">Rechtssicherheit &amp; DSGVO</span>
    <h4>Impressum, Datenschutz &amp; Consent</h4>
    <p>6 Min · Leitfaden</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.domains') }}" class="rel">
    <span class="mini">Domains &amp; SSL</span>
    <h4>Eigene Domain verbinden</h4>
    <p>4 Min · Anleitung</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
