@extends('help.article-layout')

@section('meta_title', 'Analytics verstehen — Besucher, Klicks & Conversions | ' . config('app.name'))
@section('meta_description', 'Das Analytics-Dashboard von Wayvio verstehen: Besucher, Seitenaufrufe, Klickraten und Conversion-Ziele auswerten und optimieren.')

@section('breadcrumb_trail')
  <span class="here">Analytics</span>
@endsection



@section('art_title')Analytics verstehen — Besucher, Klicks und Conversions richtig lesen.@endsection

@section('art_lede')
  Das Analytics-Dashboard zeigt dir, wie Besucher mit deinem Hub interagieren. Dieser Leitfaden erklärt alle Kennzahlen, zeigt dir wie du Schwachstellen erkennst und hilft dir, datenbasiert zu optimieren.
@endsection

@section('content')

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Kein externes Tracking nötig</h4>
      <p>{{ config('app.name') }} trackt Besucher und Klicks datenschutzkonform ohne Cookies. Du brauchst kein Google Analytics oder Meta Pixel — alle relevanten Daten sind direkt im Dashboard.</p>
    </div>
  </div>

  <h2 id="uebersicht">Das Analytics-Dashboard im Überblick</h2>
  <p>Öffne <strong>Dashboard → Analytics</strong>. Du siehst oben vier Kernindikatoren für den gewählten Zeitraum (Standard: letzte 30 Tage):</p>

  <table>
    <thead>
      <tr><th>Kennzahl</th><th>Was sie bedeutet</th><th>Gut, wenn…</th></tr>
    </thead>
    <tbody>
      <tr><td><strong>Besucher</strong></td><td>Eindeutige Sitzungen auf deiner Seite</td><td>Wächst über Zeit</td></tr>
      <tr><td><strong>Seitenaufrufe</strong></td><td>Gesamtanzahl der Seiten-Loads</td><td>Näher an Besuchern = weniger Bounces</td></tr>
      <tr><td><strong>Klicks</strong></td><td>Gesamtklicks auf alle Blöcke und Links</td><td>Steigt proportional zu Besuchern</td></tr>
      <tr><td><strong>CTR</strong></td><td>Klicks ÷ Besucher (Click-Through-Rate)</td><td>≥ 30 % ist ein solider Ausgangswert</td></tr>
    </tbody>
  </table>

  <h2 id="klick-details">Klickdaten pro Block auswerten</h2>
  <p>Im Tab <strong>Klicks</strong> siehst du jeden einzelnen Block mit der Anzahl seiner Klicks und der jeweiligen Klickrate. So erkennst du sofort, welche Elemente Aufmerksamkeit bekommen und welche ignoriert werden.</p>

  <h3>Was du aus den Klickdaten ableitest</h3>
  <ul>
    <li><strong>Primärer CTA hat wenig Klicks</strong> → Er ist zu weit unten oder nicht auffällig genug — nach oben verschieben oder optisch hervorheben.</li>
    <li><strong>Social-Links haben viele Klicks, CTA wenige</strong> → Besucher wollen mehr über dich wissen, bevor sie handeln — Vertrauenselemente (Bewertungen, Beschreibung) ergänzen.</li>
    <li><strong>Ein Link hat fast alle Klicks</strong> → Das ist dein Conversion-Treiber — mehr solche Elemente oder höher positionieren.</li>
    <li><strong>Kein Block hat Klicks</strong> → Besucher springen ab — prüfe Ladezeit, Mobile-Darstellung und ob der erste sichtbare Inhalt sofort relevant ist.</li>
  </ul>

  <h2 id="zeitraum">Zeiträume und Vergleiche</h2>
  <p>Nutze den Zeitraumwähler oben rechts im Dashboard, um verschiedene Perioden zu vergleichen. Aussagekräftige Vergleiche:</p>
  <ul>
    <li><strong>Diese Woche vs. letzte Woche</strong> — kurzfristige Effekte einer Änderung messen</li>
    <li><strong>Dieser Monat vs. Vormonat</strong> — Trendentwicklung erkennen</li>
    <li><strong>Zeitraum nach Launch vs. vorher</strong> — Wirkung von Kampagnen oder Design-Änderungen</li>
  </ul>

  <h2 id="traffic-quellen">Traffic-Quellen verstehen</h2>
  <p>Im Tab <strong>Quellen</strong> siehst du, woher deine Besucher kommen:</p>
  <ul>
    <li><strong>Direkt</strong> — Bookmark, eingetippte URL oder nicht zuordenbar (häufig Social-Media-Apps)</li>
    <li><strong>Organisch</strong> — Suchmaschinen (Google, Bing)</li>
    <li><strong>Referral</strong> — Andere Websites, die auf dich verlinken</li>
    <li><strong>Social</strong> — Klicks aus Social-Media-Links (falls UTM-Parameter gesetzt)</li>
  </ul>

  <div class="callout">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>UTM-Parameter für Kampagnen nutzen</h4>
      <p>Hänge an Links in E-Mail-Newslettern oder Anzeigen UTM-Parameter an — z.&nbsp;B. <code>?utm_source=instagram&amp;utm_medium=bio</code> — um den Traffic exakt einer Quelle zuzuordnen.</p>
    </div>
  </div>

  <h2 id="optimieren">Datenbasiert optimieren</h2>
  <p>Eine einfache Optimierungsroutine, die sich in der Praxis bewährt hat:</p>
  <ol>
    <li><strong>Wöchentlich</strong> — CTR und Top-3-Klick-Blöcke kurz checken.</li>
    <li><strong>Monatlich</strong> — Trendvergleich zum Vormonat, eine konkrete Änderung testen (z.&nbsp;B. CTA-Text oder -Position).</li>
    <li><strong>Quartalsweise</strong> — Gesamtstrategie prüfen: stimmt das primäre Conversion-Ziel noch mit den Klickdaten überein?</li>
  </ol>

  <div class="callout warn">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div>
      <h4>Immer eine Änderung auf einmal</h4>
      <p>Wenn du mehrere Dinge gleichzeitig änderst, kannst du nicht zuordnen, was den Unterschied gemacht hat. Teste immer nur eine Variable pro Messzeitraum.</p>
    </div>
  </div>

@endsection

@section('related')
  <a href="{{ route('help.seitenaufbau') }}" class="rel">
    <span class="mini">Erste Schritte</span>
    <h4>Vorgeschlagener Seitenaufbau</h4>
    <p>5 Min · Leitfaden</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.quickstart.user') }}" class="rel">
    <span class="mini">Erste Schritte</span>
    <h4>Schnellstart für Nutzer</h4>
    <p>6 Min · Schritt-für-Schritt</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.seo-meta') }}" class="rel">
    <span class="mini">SEO &amp; Meta</span>
    <h4>SEO &amp; Meta-Daten</h4>
    <p>5 Min · Anleitung</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
