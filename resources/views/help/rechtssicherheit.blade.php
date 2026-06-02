@extends('help.article-layout')

@section('meta_title', 'Rechtssicherheit & DSGVO — Impressum, Datenschutz, Cookie-Consent | ' . config('app.name'))
@section('meta_description', 'Impressum-Generator, automatische Datenschutzerklärung und Cookie-Consent korrekt einrichten. Werkzeuge zur Vorbereitung — die finale Prüfung liegt bei dir.')

@section('breadcrumb_trail')
  <span class="here">Rechtssicherheit &amp; DSGVO</span>
@endsection



@section('art_title')Rechtssicherheit & DSGVO — Impressum, Datenschutz und Cookie-Consent einrichten.@endsection

@section('art_lede')
  {{ config('app.name') }} stellt Werkzeuge für Impressum, Datenschutzerklärung und Cookie-Consent bereit. Diese Generatoren helfen dir bei der Vorbereitung — die Vollständigkeit und Richtigkeit liegt als Betreiber bei dir. Im Zweifel ziehe einen Rechtsanwalt hinzu.
@endsection

@section('content')

  <div class="callout warn">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div>
      <h4>Kein Rechtsrat</h4>
      <p>{{ config('app.name') }} ist kein Rechtsanwalt und ersetzt keine rechtliche Beratung. Die bereitgestellten Werkzeuge und Informationen dienen der Orientierung. Für verbindliche Aussagen zu deiner individuellen Situation wende dich an einen Rechtsanwalt oder eine Rechtsanwaltsplattform.</p>
    </div>
  </div>

  <h2 id="impressum">Impressum-Generator richtig nutzen</h2>
  <p>Das Impressum ist in Deutschland, Österreich und der Schweiz für gewerbliche und viele nicht-gewerbliche Websites gesetzlich verpflichtend (TMG § 5, ECG § 5, DSG). Fehlende oder unvollständige Angaben können zu Abmahnungen führen.</p>
  <p>Den Impressum-Generator findest du unter <strong>Einstellungen → Rechtliches → Impressum</strong>. Fülle alle Pflichtfelder aus:</p>
  <ul>
    <li><strong>Name &amp; Anschrift</strong> — vollständige Adresse des Verantwortlichen</li>
    <li><strong>Kontakt</strong> — E-Mail-Adresse (Pflicht), Telefonnummer (empfohlen)</li>
    <li><strong>Handelsregister / USt-ID</strong> — falls vorhanden und gewerblich tätig</li>
    <li><strong>Verantwortlich für den Inhalt</strong> — bei journalistisch-redaktionellen Angeboten</li>
  </ul>
  <p>Das generierte Impressum wird automatisch, sobald die Daten eingefügt sind unter dem Punkt Rechtliches, als eigene Seite angelegt und im Footer deiner Seite verlinkt. Prüfe nach der Erstellung, ob alle Angaben korrekt und vollständig sind.</p>

  <h2 id="datenschutz">Datenschutzerklärung automatisch erstellen</h2>
  <p>Die DSGVO verpflichtet Betreiber, transparent über die Verarbeitung personenbezogener Daten zu informieren. {{ config('app.name') }} erstellt eine Basis-Datenschutzerklärung automatisch auf Grundlage deiner aktiven Blöcke und Embeds.</p>

  <h3>Was automatisch erkannt wird</h3>
  <ul>
    <li>Natives Kontaktformular → Verarbeitungshinweis für Formulardaten wird ergänzt</li>
    <li>Eingebettete Videos (YouTube / Vimeo) → Hinweis auf Drittanbieter-Datenübermittlung</li>
    <li>Google Maps → Hinweis auf IP-Übermittlung an Google</li>
    <li>Hosting-Infrastruktur → Standardabschnitt für Server-Logs</li>
  </ul>

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Datenschutzerklärung nach Änderungen aktualisieren</h4>
      <p>Jedes Mal, wenn du einen neuen Drittanbieter-Embed hinzufügst (z.&nbsp;B. ein Buchungswidget oder Analytics), muss die Datenschutzerklärung entsprechend ergänzt werden. Generiere sie nach Änderungen am Block-Stapel erneut.</p>
    </div>
  </div>

  <h2 id="cookie-consent">Cookie-Consent &amp; Drittanbieter</h2>
  <p>Sobald deine Seite Cookies setzt oder externe Scripts lädt, die Cookies setzen könnten, ist eine Einwilligung (Consent) der Besucher erforderlich — das gilt insbesondere für:</p>
  <ul>
    <li>YouTube / Vimeo (setzen Marketing-Cookies beim Laden)</li>
    <li>Google Maps (übermittelt IP-Adressen)</li>
    <li>Instagram-Embeds</li>
    <li>Buchungswidgets von Drittanbietern</li>
    <li>Analytics-Tools (falls genutzt)</li>
  </ul>
  <p>{{ config('app.name') }} zeigt für blockierte Embeds automatisch einen Consent-Platzhalter an: Der Besucher sieht zuerst einen Hinweis und kann den Inhalt aktiv freischalten. Das entspricht den Anforderungen des ePrivacy-Rechts und der DSGVO.</p>

  <h2 id="verantwortung">Verantwortung &amp; Haftungsausschluss</h2>
  <p>Als Betreiber deiner Seite bist du für alle veröffentlichten Inhalte verantwortlich — unabhängig davon, ob Texte, Bilder oder Links von dir stammen oder aus externen Quellen eingebettet wurden.</p>

  <table>
    <thead>
      <tr><th>Inhalt</th><th>Verantwortung</th></tr>
    </thead>
    <tbody>
      <tr><td>Eigene Texte &amp; Bilder</td><td>Vollständig bei dir als Betreiber</td></tr>
      <tr><td>Eingebettete externe Inhalte</td><td>Du als Einbinder — prüfe Urheberrecht &amp; Lizenzen</td></tr>
      <tr><td>Verlinkte externe Seiten</td><td>Keine Haftung für verlinkte Inhalte, aber bewusst verlinken</td></tr>
      <tr><td>Formulardaten &amp; Anfragen</td><td>Du als Empfänger — Speicherung sicherstellen</td></tr>
    </tbody>
  </table>

  <div class="callout">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
    <div>
      <h4>Unsicher bei einem konkreten Fall?</h4>
      <p>Schreib uns — wir können allgemeine Orientierung geben, jedoch keine rechtliche Beratung ersetzen.</p>
    </div>
  </div>

@endsection

@section('related')
  <a href="{{ route('help.embed-blocks') }}" class="rel">
    <span class="mini">Embeds &amp; Formulare</span>
    <h4>Embeds richtig einbinden</h4>
    <p>5 Min · Leitfaden</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.quickstart.user') }}" class="rel">
    <span class="mini">Erste Schritte</span>
    <h4>Schnellstart für Nutzer</h4>
    <p>6 Min · Schritt-für-Schritt</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.faq') }}" class="rel">
    <span class="mini">Q&amp;A</span>
    <h4>Häufige Fragen &amp; Antworten</h4>
    <p>Alle Themen</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
