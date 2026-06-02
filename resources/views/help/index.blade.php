@extends('help.layout')

@section('meta_title', config('app.name') . ' — Hilfezentrum')
@section('meta_description', 'Anleitungen, Schritt-für-Schritt-Guides und Antworten — für deinen ersten Block-Stapel bis zum White-Label-Setup für Agenturen.')

@php
  $chevSvg = '<svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
  $arrowSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
@endphp

@section('main')

<!-- ===== HERO ===== -->
<section class="hero">
  <div class="container">
    <span class="eyebrow"><span class="dot"></span>Hilfezentrum · {{ config('app.name') }}</span>
    <h1 class="hero-title">Wie können wir <em>helfen?</em></h1>
    <p class="hero-sub">Anleitungen, Schritt-für-Schritt-Guides und Antworten — für deinen ersten Block-Stapel bis zum White-Label-Setup für Agenturen.</p>

  </div>
</section>

<!-- ===== POPULAR ===== -->
<section class="block" style="padding-top:64px;padding-bottom:24px">
  <div class="container">
    <div class="popular-row">
      <div class="section-head" style="margin-bottom:0">
        <div class="section-eyebrow">Beliebte Artikel</div>
        <h2 class="section-title">Diese Anleitungen helfen am häufigsten weiter.</h2>
      </div>
    </div>

    <div class="popular-grid">
      <a href="{{ route('help.quickstart.user') }}" class="pop">
        <span class="pop-mini">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Erste Schritte
        </span>
        <h3>In 10 Minuten von Null zu live: dein erster Block-Stapel.</h3>
        <p>Vorlage wählen, Blöcke stapeln, Domain verbinden — der schnellste Weg zur ersten veröffentlichten Seite.</p>
        <div class="pop-foot">
          <span>6 Min · Schritt-für-Schritt</span>
          <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
      </a>

      <a href="{{ route('help.domains') }}" class="pop">
        <span class="pop-mini">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>
          Domains &amp; SSL
        </span>
        <h3>Eigene Domain verbinden — DNS sauber konfigurieren.</h3>
        <p>A-Record, CNAME und automatisches SSL: was du bei deinem Domain-Provider eintragen musst.</p>
        <div class="pop-foot">
          <span>4 Min · Anleitung</span>
          <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
      </a>

      <a href="{{ route('help.embed-blocks') }}" class="pop">
        <span class="pop-mini">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          Embeds &amp; Formulare
        </span>
        <h3>Embeds richtig einbinden — Instagram, YouTube, Maps und Formulare.</h3>
        <p>Welche Embeds erlaubt sind, worauf du bei Consent achten musst und wie du Performance schützt.</p>
        <div class="pop-foot">
          <span>5 Min · Leitfaden</span>
          <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
      </a>

      <a href="{{ route('help.analytics') }}" class="pop">
        <span class="pop-mini">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Analytics
        </span>
        <h3>Analytics verstehen — Besucher, Klicks und Conversions richtig lesen.</h3>
        <p>Alle Kennzahlen erklärt: CTR, Traffic-Quellen und wie du datenbasiert optimierst.</p>
        <div class="pop-foot">
          <span>5 Min · Leitfaden</span>
          <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
      </a>
    </div>
  </div>
</section>

<!-- ===== CATEGORIES ===== -->
<section class="block" style="padding-top:48px">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Kategorien</div>
      <h2 class="section-title">Wähl ein Thema.</h2>
      <p class="section-sub">Nach Themen sortiert — von Onboarding bis White-Label. Tipp: jede Kategorie hat eine eigene Übersicht mit allen zugehörigen Artikeln.</p>
    </div>

    <div class="cat-grid">

      <!-- Erste Schritte -->
      <article class="cat">
        <div class="cat-head">
          <div class="badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          </div>
        </div>
        <h3>Erste Schritte</h3>
        <p>Account einrichten, Vorlage auswählen, deinen ersten Block-Stapel veröffentlichen.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.quickstart.user') }}">Schnellstart: Hub in 10 Minuten live <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
          <li><a href="{{ route('help.seitenaufbau') }}">Vorgeschlagener Seitenaufbau <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
          <li><a href="{{ route('help.analytics') }}">Analytics &amp; Klickdaten verstehen <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
        </ul>
      </article>

      <!-- Block-Stapel & Editor -->
      <article class="cat">
        <div class="cat-head">
          <div class="badge dark">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="4" rx="1"/><rect x="4" y="10" width="16" height="4" rx="1"/><rect x="4" y="16" width="16" height="4" rx="1"/></svg>
          </div>
        </div>
        <h3>Block-Stapel &amp; Editor</h3>
        <p>Wie du Blöcke aussuchst, stapelst, ordnest und individuell anpasst — kein klassischer Editor, sondern reines Stapeln.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.block-editor') }}">Alle Block-Typen im Überblick <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
          <li><a href="{{ route('help.header-modi') }}">Header-Modi: Titel, Business, Minimal &amp; Hero <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
        </ul>
      </article>

      <!-- Domains & SSL -->
      <article class="cat">
        <div class="cat-head">
          <div class="badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>
          </div>
        </div>
        <h3>Domains &amp; SSL</h3>
        <p>Eigene Domain verbinden, Sub-Domains nutzen und automatische SSL-Zertifikate verstehen.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.domains') }}">Domain verbinden — SSL wird automatisch eingerichtet <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
        </ul>
      </article>

      <!-- Rechtssicherheit & DSGVO -->
      <article class="cat">
        <div class="cat-head">
          <div class="badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
          </div>
        </div>
        <h3>Rechtssicherheit &amp; DSGVO</h3>
        <p>Impressum, Datenschutz und Cookie-Consent — Werkzeuge zur Vorbereitung. Die finale Prüfung liegt bei dir.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.rechtssicherheit') }}">Impressum, DSGVO &amp; Cookie-Consent <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
        </ul>
      </article>

      <!-- SEO & Meta -->
      <article class="cat">
        <div class="cat-head">
          <div class="badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><path d="M11 8v6M8 11h6"/></svg>
          </div>
        </div>
        <h3>SEO &amp; Meta-Daten</h3>
        <p>Title-Tags, Beschreibungen, Open-Graph-Bilder und technische Grundlagen für bessere Auffindbarkeit.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.seo-meta') }}">Title, Meta, Open Graph &amp; Indexierung <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
        </ul>
      </article>

      <!-- Embeds & Formulare -->
      <article class="cat">
        <div class="cat-head">
          <div class="badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          </div>
        </div>
        <h3>Embeds &amp; Formulare</h3>
        <p>Instagram, YouTube, Spotify, Maps, Calendly — und native Kontaktformulare ohne Drittanbieter.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.embed-blocks') }}">Embeds einbinden: YouTube, Maps, Instagram &amp; Co. <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
        </ul>
      </article>

      <!-- Q&A & Häufige Fragen -->
      <article class="cat">
        <div class="cat-head">
          <div class="badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
        </div>
        <h3>Q&amp;A &amp; Häufige Fragen</h3>
        <p>Setup, Embeds, Domains, SEO und Agentur-Workflows — direkte Antworten auf die häufigsten Fragen.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.faq') }}">Alle Fragen &amp; Antworten <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
        </ul>
      </article>

      <!-- Account & Abrechnung -->
      <article class="cat">
        <div class="cat-head">
          <div class="badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M3 21a9 9 0 0118 0"/></svg>
          </div>
        </div>
        <h3>Account &amp; Abrechnung</h3>
        <p>Plan wechseln, Zahlungsdaten, Rechnungen herunterladen, Konto kündigen.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.account-abrechnung') }}">Plan, Zahlung, 2FA &amp; Konto kündigen <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
        </ul>
      </article>

      <!-- Agentur & White-Label -->
      <article class="cat">
        <div class="cat-head">
          <div class="badge dark">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21a6 6 0 0112 0"/><path d="M14 21a5 5 0 017-4.6"/></svg>
          </div>
        </div>
        <h3>Agentur &amp; White-Label</h3>
        <p>Mehrere Kunden zentral verwalten, eigene Marke einsetzen, Kunden onboarden.</p>
        <ul class="cat-list">
          <li><a href="{{ route('help.quickstart.agency') }}">Agentur-Schnellstart &amp; Kunden onboarden <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
          <li><a href="{{ route('help.white-label') }}">White-Label: eigenes Logo &amp; Branding <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a></li>
        </ul>
      </article>

    </div>
  </div>
</section>

<!-- ===== QUICK START ===== -->
<section class="block" style="padding-top:24px">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Schnellstart</div>
      <h2 class="section-title">Direkt loslegen — zwei Pfade.</h2>
      <p class="section-sub">Ob du eine erste Seite veröffentlichst oder mehrere Kunden-Hubs betreust: hier ist der schnellste Einstieg.</p>
    </div>

    <div class="quick">
      <a href="{{ route('help.quickstart.user') }}" class="quick-card">
        <div class="ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        </div>
        <h3>Schnellstart für Nutzer</h3>
        <p>In 10 Minuten von leerer Vorlage zu veröffentlichter Seite: Profil, Block-Stapel, CTA und Links.</p>
        <span class="qcta">Benutzer-Handbuch öffnen
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </span>
      </a>

      <a href="{{ route('help.quickstart.agency') }}" class="quick-card alt">
        <div class="ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21a6 6 0 0112 0"/><path d="M14 21a5 5 0 017-4.6"/></svg>
        </div>
        <h3>Schnellstart für Agenturen</h3>
        <p>Kunden-Hubs einrichten, Bereitstellung standardisieren und teamübergreifend ausrollen.</p>
        <span class="qcta">Agentur-Leitfaden öffnen
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </span>
      </a>
    </div>
  </div>
</section>

<!-- ===== CONTACT ===== -->
<section class="block" style="padding-top:24px">
  <div class="container">
    <div class="contact">
      <div>
        <h3>Keine passende Antwort gefunden?</h3>
        <p>Schreib uns direkt — wir antworten in der Regel innerhalb von 24 Stunden, an Werktagen meist deutlich schneller. Pro- und Agentur-Kunden mit priorisiertem Support.</p>
      </div>
      <div class="contact-actions">
        <a href="{{ route('help.faq') }}" class="btn btn-ghost">Alle Q&amp;A</a>
        <a href="mailto:support@example.com" class="btn btn-primary">Support kontaktieren
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
    </div>
  </div>
</section>

@endsection
