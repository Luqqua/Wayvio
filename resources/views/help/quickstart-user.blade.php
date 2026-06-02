@extends('help.article-layout')

@section('meta_title', 'Schnellstart für Nutzer — Hub in 10 Minuten live | ' . config('app.name'))
@section('meta_description', 'Schritt-für-Schritt-Guide für Nutzer: Account einrichten, Template wählen, Block-Stapel aufbauen und Seite veröffentlichen.')

@section('breadcrumb_trail')
  <span class="here">Erste Schritte</span>
@endsection



@section('art_title')Schnellstart für Nutzer — von leerer Vorlage zur veröffentlichten Seite.@endsection

@section('art_lede')
  Dieser Guide führt dich in 10 Minuten durch Account, Template-Auswahl, Block-Stapel und Live-Schaltung. Kein Code, keine technischen Vorkenntnisse nötig — {{ config('app.name') }} ist die All-in-One-Lösung direkt out of the box.
@endsection

@section('content')

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Was ist der Block-Stapel-Mechanismus?</h4>
      <p>{{ config('app.name') }} verwendet keinen klassischen Editor. Du wählst vorgefertigte Blöcke (z.&nbsp;B. Profil, Links, CTA, Galerie) und stapelst sie untereinander. Die Reihenfolge bestimmst du per Drag &amp; Drop — fertig.</p>
    </div>
  </div>

  <h2 id="schritt-1">1. Account einrichten</h2>
  <p>Registriere dich mit deiner E-Mail-Adresse und bestätige dein Konto über den zugesandten Link. Im nächsten Schritt legst du deinen Anzeigenamen und optional ein Profilbild fest — beides ist später jederzeit änderbar.</p>

  <h2 id="schritt-2">2. Template auswählen</h2>
  <p>{{ config('app.name') }} bietet vorgefertigte Templates für unterschiedliche Branchen und Ziele: Gastro, Handwerk, Kreative, Link-in-Bio und mehr. Wähl das Template, das deinem Ziel am nächsten kommt. Du kannst alle Blöcke danach beliebig anpassen oder ersetzen.</p>

  <h2 id="schritt-3">3. Block-Stapel aufbauen</h2>
  <p>Im Editor siehst du deinen aktuellen Block-Stapel auf der linken Seite und eine Live-Vorschau rechts. Füge Blöcke hinzu, sortiere sie per Drag &amp; Drop neu und passe Texte, Farben und Bilder direkt an.</p>

  <h3>Empfohlene Basisstruktur</h3>
  <ol>
    <li><strong>Profil-Block</strong> — Name, kurze Beschreibung, Bild</li>
    <li><strong>Kernangebot</strong> — Was du anbietest, in 1–2 Sätzen</li>
    <li><strong>Primärer CTA</strong> — Buchung, Kontaktformular oder wichtigster Link ganz oben</li>
    <li><strong>Vertrauenselemente</strong> — Bewertungen, Referenzen, Logos</li>
    <li><strong>Weitere Links</strong> — Social Media, Portfolio, Sekundär-CTAs</li>
  </ol>

  <div class="callout warn">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div>
      <h4>Einen klaren Fokus behalten</h4>
      <p>Zu viele Blöcke verwässern das Ziel deiner Seite. Starte mit dem Minimum (Profil, CTA, 2–3 Links) und ergänze erst dann, wenn du weißt, was deine Besucher brauchen.</p>
    </div>
  </div>

  <h2 id="schritt-4">4. SEO &amp; Meta-Daten setzen</h2>
  <p>Unter <strong>Einstellungen → SEO</strong> kannst du Title-Tag, Meta-Description und das Open-Graph-Bild anpassen. {{ config('app.name') }} generiert automatisch eine Sitemap und setzt technische SEO-Grundlagen — du musst nur den redaktionellen Teil ergänzen.</p>
  <ul>
    <li><strong>Title</strong> — max. 60 Zeichen, dein Hauptkeyword vorne</li>
    <li><strong>Meta-Description</strong> — max. 155 Zeichen, Call-to-Action einbauen</li>
    <li><strong>OG-Bild</strong> — 1200 × 630 px, wird bei Social-Media-Shares angezeigt</li>
  </ul>

  <h2 id="schritt-5">5. Rechtssicherheit prüfen</h2>
  <p>{{ config('app.name') }} bietet Werkzeuge für Impressum und Datenschutzerklärung an. <strong>Wichtig:</strong> Diese Generatoren unterstützen dich bei der Vorbereitung — die Vollständigkeit und finale Kontrolle liegt bei dir als Betreiber. Im Zweifel bitte einen Rechtsanwalt hinzuziehen.</p>

  <h2 id="schritt-6">6. Veröffentlichen &amp; Domain verbinden</h2>
  <p>Klicke auf <em>Veröffentlichen</em> — deine Seite ist sofort über die kostenlose Wayvio-Sub-Domain erreichbar. Optional kannst du in <strong>Einstellungen → Domains</strong> eine eigene Domain verbinden.</p>

  <h3>Vor dem Launch prüfen</h3>
  <ul>
    <li>Primärer CTA ist ohne Scrollen sichtbar</li>
    <li>Alle Links öffnen sich korrekt (auch auf Mobile)</li>
    <li>Vorschau auf dem Smartphone angesehen</li>
    <li>Meta-Title und -Description gesetzt</li>
    <li>Impressum und Datenschutz verlinkt</li>
  </ul>

  <div class="callout">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
    <div>
      <h4>Fragen während des Setups?</h4>
      <p>Im Q&amp;A-Bereich findest du Antworten auf die häufigsten Fragen — oder schreib uns direkt.</p>
    </div>
  </div>

@endsection

@section('related')
  <a href="{{ route('help.domains') }}" class="rel">
    <span class="mini">Domains &amp; SSL</span>
    <h4>Eigene Domain verbinden</h4>
    <p>4 Min · Anleitung</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.embed-blocks') }}" class="rel">
    <span class="mini">Embeds &amp; Formulare</span>
    <h4>Embeds richtig einbinden</h4>
    <p>5 Min · Leitfaden</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.faq') }}" class="rel">
    <span class="mini">Q&amp;A</span>
    <h4>Häufige Fragen &amp; Antworten</h4>
    <p>Alle Themen</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
