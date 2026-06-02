@extends('help.article-layout')

@section('meta_title', 'Block-Stapel & Editor — alle Block-Typen & Anpassungen | ' . config('app.name'))
@section('meta_description', 'Wie der Block-Stapel-Mechanismus funktioniert: Blöcke hinzufügen, sortieren, anpassen. Übersicht aller Block-Typen, Farben, Schriften und Medien.')

@section('breadcrumb_trail')
  <span class="here">Block-Stapel &amp; Editor</span>
@endsection



@section('art_title')Block-Stapel & Editor — Blöcke wählen, stapeln und anpassen.@endsection

@section('art_lede')
  {{ config('app.name') }} verwendet keinen klassischen Texteditor. Stattdessen wählst du vorgefertigte Blöcke, stapelst sie untereinander und passt Inhalt, Farben und Medien direkt an — ohne Code, ohne Drag-and-Drop-Chaos.
@endsection

@section('content')

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Das Prinzip in einem Satz</h4>
      <p>Jede Seite ist ein vertikaler Stapel von Blöcken. Du entscheidest, welche Blöcke enthalten sind und in welcher Reihenfolge sie erscheinen — kein leeres weißes Feld, kein Pixel-Rücken.</p>
    </div>
  </div>

  <h2 id="block-hinzufuegen">Block hinzufügen &amp; neu sortieren</h2>
  <p>Im Editor siehst du auf der linken Seite deinen aktuellen Stapel und rechts eine Live-Vorschau. Um einen Block hinzuzufügen:</p>
  <ol>
    <li>Klicke auf <em>Block hinzufügen</em> unterhalb des letzten Elements.</li>
    <li>Wähle aus der Block-Bibliothek den gewünschten Typ aus.</li>
    <li>Der neue Block im Stapel — ziehe ihn per Drag &amp; Drop an die gewünschte Position.</li>
  </ol>

  <h2 id="block-typen">Übersicht aller Block-Typen</h2>

  <h3>Links &amp; Kontakt</h3>
  <ul>
    <li><strong>Link</strong> — Klickbarer Link zu jeder URL, mit anpassbarem Stil und Icon.</li>
    <li><strong>E-Mail</strong> — Klickbare E-Mail-Adresse, öffnet direkt den Mail-Dialog.</li>
    <li><strong>Telefon</strong> — Klickbare Telefonnummer, öffnet direkt den Anruf-Dialog.</li>
    <li><strong>Digitale Visitenkarte (vCard)</strong> — Herunterladbarer Kontakt mit Name, Adressen, Telefon und E-Mail.</li>
  </ul>

  <h3>Inhalt &amp; Struktur</h3>
  <ul>
    <li><strong>Text</strong> — Freier Fließtext, nicht klickbar. Ideal für kurze Beschreibungen oder „Über mich".</li>
    <li><strong>Überschrift</strong> — Visueller Abschnittstrenner, um Blöcke thematisch zu gruppieren.</li>
    <li><strong>Separator</strong> — Horizontale Trennlinie für mehr Struktur.</li>
    <li><strong>Abstandshalter</strong> — Leerer Zwischenraum in einstellbarer Höhe.</li>
  </ul>

  <h3>Angebote &amp; Vertrauen</h3>
  <ul>
    <li><strong>Leistungen</strong> — Mobile-first Liste für Menüs, Produkte oder Services mit Preisen.</li>
    <li><strong>USP Cards</strong> — Deine Vorteile und Benefits kompakt in Karten dargestellt.</li>
    <li><strong>Social Proof</strong> — Bis zu 5 Testimonials mit Sternebewertung und Kundenname, ohne externe Skripte.</li>
  </ul>

  <h3>Kontakt &amp; Info</h3>
  <ul>
    <li><strong>Kontaktformular</strong> — Natives Formular ohne Drittanbieter. Einsendungen landen direkt in deinem Dashboard.</li>
    <li><strong>Öffnungszeiten</strong> — Strukturierte Wochentagsübersicht mit Uhrzeiten.</li>
    <li><strong>Impressum</strong> — Rechtliche Pflichtangaben für deine Seite.</li>
  </ul>

  <h3>Embeds</h3>
  <ul>
    <li><strong>Smart Embed</strong> — Einbettung für YouTube, Instagram, Spotify, Google Maps, Calendly und weitere Anbieter — mit automatischem Consent-Banner vor dem Laden.</li>
  </ul>

  <div class="callout warn">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div>
      <h4>Weniger ist mehr</h4>
      <p>Seiten mit mehr als 10–12 Blöcken verlieren Fokus und konvertieren schlechter. Starte mit dem Minimum, und ergänze nur dann, wenn du weißt, was deine Besucher suchen.</p>
    </div>
  </div>

  <h2 id="farben-schriften">Farben &amp; Schriften pro Seite</h2>
  <p>Unter <strong>Einstellungen → Design</strong> kannst du Farb-Schema und Schriftpaarung für die gesamte Seite festlegen. Änderungen gelten global für alle Blöcke — einzelne Blöcke können in den meisten Fällen zusätzlich individuell überschrieben werden.</p>

  <table>
    <thead>
      <tr><th>Einstellung</th><th>Wo zu finden</th><th>Wirkung</th></tr>
    </thead>
    <tbody>
      <tr><td>Primärfarbe</td><td>Design → Farben</td><td>Buttons, Links, Akzente</td></tr>
      <tr><td>Hintergrundfarbe</td><td>Design → Farben</td><td>Seiten-Hintergrund</td></tr>
      <tr><td>Schriftpaarung</td><td>Design → Typografie</td><td>Überschriften + Fließtext</td></tr>
      <tr><td>Dunkelmodus</td><td>Design → Erscheinungsbild</td><td>Automatisch oder erzwungen</td></tr>
    </tbody>
  </table>


@endsection

@section('related')
  <a href="{{ route('help.quickstart.user') }}" class="rel">
    <span class="mini">Erste Schritte</span>
    <h4>Schnellstart für Nutzer</h4>
    <p>6 Min · Schritt-für-Schritt</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.embed-blocks') }}" class="rel">
    <span class="mini">Embeds &amp; Formulare</span>
    <h4>Embeds richtig einbinden</h4>
    <p>5 Min · Leitfaden</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.seo-meta') }}" class="rel">
    <span class="mini">SEO &amp; Meta-Daten</span>
    <h4>Title, Description &amp; Open Graph</h4>
    <p>5 Min · Anleitung</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
