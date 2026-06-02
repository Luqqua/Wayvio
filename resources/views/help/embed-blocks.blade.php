@extends('help.article-layout')

@section('meta_title', 'Embeds richtig einbinden — Instagram, YouTube, Maps & Formulare | ' . config('app.name'))
@section('meta_description', 'Leitfaden für Embed-Blöcke: wann Embeds sinnvoll sind, wie du Performance und Consent sicherstellst, und welche Anbieter unterstützt werden.')

@section('breadcrumb_trail')
  <span class="here">Embeds &amp; Formulare</span>
@endsection



@section('art_title')Embeds richtig einbinden — zuverlässig, schnell und datenschutzkonform.@endsection

@section('art_lede')
  Dieser Leitfaden zeigt, wann du einen Embed-Block einsetzen solltest, wie du Performance und Ladezeit schützt und wie {{ config('app.name') }} den Consent für externe Anbieter automatisch regelt.
@endsection

@section('content')

  <p>{{ config('app.name') }} unterstützt Embeds für Instagram, YouTube, Spotify, Google Maps, Calendly und weitere Anbieter direkt über den Embed-Block. Nutze Embeds gezielt — native Blöcke sind für einfache Inhalte in der Regel schneller.</p>

  <h2 id="wann-embeds">1. Wann macht ein Embed Sinn?</h2>
  <p>Embeds sind die richtige Wahl, wenn:</p>
  <ul>
    <li>kein nativer Block für den Inhalt existiert (z.&nbsp;B. Buchungswidgets, externe Formulare)</li>
    <li>das eingebettete Element zentral für deine Conversion ist</li>
    <li>der Anbieter stabiles, responsives Embedding unterstützt</li>
    <li>du komplexe interaktive Inhalte (Karten, Kalender, Musik) einbinden willst</li>
  </ul>

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Native Blöcke bevorzugen</h4>
      <p>Für einfache Links zu Instagram, YouTube oder Spotify reichen meistens native Link-Blöcke. Embeds laden zusätzliche Scripts und können die Ladezeit spürbar erhöhen.</p>
    </div>
  </div>

  <h2 id="performance">2. Performance-Grundregeln</h2>
  <p>Embeds können die Ladezeit deiner Seite erheblich beeinflussen, besonders auf mobilen Verbindungen. Halte dich an diese Grundregeln:</p>
  <ul>
    <li><strong>Nur notwendige Embeds aktivieren</strong> — alles andere als nativen Link einbinden.</li>
    <li><strong>Schwerere Embeds weiter unten platzieren</strong> — Maps und Kalender gehören nicht in den sichtbaren Bereich beim Laden.</li>
    <li><strong>Mobile Performance nach jeder Änderung prüfen</strong> — Embed-Verhalten auf kleinen Bildschirmen ist oft anders als erwartet.</li>
    <li><strong>Maximal 2–3 Embeds pro Seite</strong> — jeder zusätzliche Embed kostet Ladezeit und Aufmerksamkeit.</li>
  </ul>

  <div class="callout warn">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div>
      <h4>Mehrere schwere Embeds über dem Fold vermeiden</h4>
      <p>Instagram, YouTube und Maps gleichzeitig im sichtbaren Bereich (above the fold) können den First Contentful Paint auf unter Google-Schwellenwerte drücken und das Ranking beeinflussen.</p>
    </div>
  </div>

  <h2 id="consent">3. Datenschutz und Consent</h2>
  <p>{{ config('app.name') }} blendet vor externen Embeds automatisch einen Consent-Banner ein. Erst wenn der Besucher zustimmt, wird das Embed geladen — lehnt er ab, erscheint an seiner Stelle ein Link im eingestellten Stil. So wird kein Tracking-Script geladen, bevor eine aktive Einwilligung vorliegt.</p>

  <div class="callout">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg></div>
    <div>
      <h4>Du trägst die rechtliche Verantwortung</h4>
      <p>Der automatische Consent-Banner entbindet dich nicht von deiner Pflicht als Seitenbetreiber: Alle eingebundenen Drittanbieter müssen in deiner <strong>Datenschutzerklärung</strong> aufgeführt sein. Im Zweifel bitte einen Rechtsanwalt hinzuziehen.</p>
    </div>
  </div>

  <h2 id="anbieter">4. Unterstützte Anbieter</h2>

  <table>
    <thead>
      <tr><th>Anbieter</th><th>Typ</th></tr>
    </thead>
    <tbody>
      <tr><td>YouTube</td><td>Video / Playlist</td></tr>
      <tr><td>Instagram</td><td>Beitrag</td></tr>
      <tr><td>Spotify</td><td>Track / Album / Playlist / Podcast</td></tr>
      <tr><td>Google Maps</td><td>Karte / Standort</td></tr>
      <tr><td>Calendly</td><td>Terminbuchung</td></tr>
      <tr><td>Tally</td><td>Formular</td></tr>
      <tr><td>Gumroad</td><td>Produkt / Shop</td></tr>
      <tr><td>Kit</td><td>Newsletter-Formular</td></tr>
      <tr><td>Resmio</td><td>Restaurantbuchung / Speisekarte</td></tr>
    </tbody>
  </table>

  <h2 id="dos-donts">5. Dos &amp; Don'ts</h2>

  <h3>Mach das</h3>
  <ul>
    <li>Nur hochwertige, conversion-relevante Embeds einsetzen</li>
    <li>Mobile Verhalten nach dem Einbetten testen</li>
    <li>Alle eingebundenen Drittanbieter in der Datenschutzerklärung dokumentieren</li>
  </ul>

  <h3>Lass das besser sein</h3>
  <ul>
    <li>Mehrere schwere Embeds im sichtbaren Bereich stapeln</li>
    <li>Ungeprüfte iFrame-Scripts von unbekannten Quellen einbinden</li>
    <li>Embeds für Inhalte nutzen, die auch als nativer Block verfügbar wären</li>
  </ul>

@endsection

@section('related')
  <a href="{{ route('help.quickstart.user') }}" class="rel">
    <span class="mini">Erste Schritte</span>
    <h4>Schnellstart für Nutzer</h4>
    <p>6 Min · Schritt-für-Schritt</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.domains') }}" class="rel">
    <span class="mini">Domains &amp; SSL</span>
    <h4>Domain verbinden &amp; DNS einrichten</h4>
    <p>4 Min · Anleitung</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.faq') }}" class="rel">
    <span class="mini">Q&amp;A</span>
    <h4>Häufige Fragen zu Embeds</h4>
    <p>Alle Antworten</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
