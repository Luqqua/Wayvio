@extends('help.article-layout')

@section('meta_title', 'White-Label — Agency-Domain und Branding für Kunden-Hubs | ' . config('app.name'))
@section('meta_description', 'White-Label für Agenturen: eigene Domain für alle Hubs und eigenes Logo statt Wayvio-Branding — alles an einem Ort konfiguriert.')

@section('breadcrumb_trail')
  <span class="here">White-Label</span>
@endsection



@section('art_title')White-Label — eigene Domain und Branding auf allen Kunden-Hubs.@endsection

@section('art_lede')
  White-Label macht {{ config('app.name') }} für deine Kunden unsichtbar. Du hinterlegst eine eigene Agentur-Domain und ein eigenes Logo — auf jedem Hub, den du verwaltest.
@endsection

@section('content')

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Wo du die Einstellungen findest</h4>
      <p>Alle White-Label-Optionen sind unter <strong>Branding</strong> in der Sidebar zusammengefasst. Der Eintrag ist nur für Agentur-Accounts sichtbar.</p>
    </div>
  </div>

  <h2 id="warum">Warum White-Label?</h2>
  <p>Als Agentur lieferst du Kunden fertige Hubs — die Infrastruktur dahinter ist deine Sache. White-Label stellt sicher, dass Besucher ausschließlich deinen Markennamen und deine Domain sehen. Du wirkst professioneller, baust Vertrauen auf und kannst das Produkt unter deinem eigenen Label anbieten, ohne eigene Infrastruktur aufzubauen.</p>

  <h2 id="agency-domain">Agency-Domain</h2>
  <p>Du kannst eine einzelne Domain hinterlegen, die für alle deine Hubs gilt. Jeder Hub ist dann über diese Domain erreichbar — die URL-Endung des Hubs wird als Pfad angehängt:</p>
  <ul>
    <li><code>deineagentur.com/kunde-a</code></li>
    <li><code>deineagentur.com/kunde-b</code></li>
  </ul>
  <p>Die Domain wird unter <strong>Branding → Agency-Domain</strong> eingetragen. Nach dem Speichern wird die Domain automatisch verifiziert. Solange die Verifizierung noch aussteht, ist die Domain noch nicht aktiv.</p>

  <h2 id="agency-branding">Agency-Branding</h2>
  <p>Im öffentlichen Hub erscheint standardmäßig das {{ config('app.name') }}-Logo am unteren Seitenrand. Mit Agency-Branding ersetzt du dieses Logo durch dein eigenes.</p>
  <p>Du lädst ein Logo hoch (JPG, PNG oder WebP) und kannst optional eine URL hinterlegen, auf die das Logo verlinkt — zum Beispiel deine Agentur-Website. Das Logo gilt global für alle deine Hubs.</p>

  <div class="callout">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
    <div>
      <h4>Vorschau direkt in den Einstellungen</h4>
      <p>Die Branding-Seite zeigt eine Live-Vorschau, wie das Logo aussieht — entweder dein hochgeladenes Bild oder das aktuelle {{ config('app.name') }}-Branding als Fallback, solange noch kein Logo hinterlegt ist.</p>
    </div>
  </div>

@endsection

@section('related')
  <a href="{{ route('help.quickstart.agency') }}" class="rel">
    <span class="mini">Agentur &amp; White-Label</span>
    <h4>Agentur-Account &amp; Hub-Verwaltung</h4>
    <p>4 Min · Übersicht</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.domains') }}" class="rel">
    <span class="mini">Domains &amp; SSL</span>
    <h4>Domain verbinden — SSL wird automatisch eingerichtet</h4>
    <p>4 Min · Anleitung</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.quickstart.user') }}" class="rel">
    <span class="mini">Erste Schritte</span>
    <h4>Schnellstart für Nutzer</h4>
    <p>6 Min · Schritt-für-Schritt</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
