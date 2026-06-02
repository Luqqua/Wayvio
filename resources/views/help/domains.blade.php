@extends('help.article-layout')

@section('meta_title', 'Eigene Domain verbinden — DNS konfigurieren | ' . config('app.name'))
@section('meta_description', 'Schritt-für-Schritt-Anleitung: eigene Domain verbinden, DNS-Einträge setzen, SSL-Zertifikat automatisch ausstellen lassen.')

@section('breadcrumb_trail')
  <span class="here">Domains &amp; SSL</span>
@endsection



@section('art_title')Eigene Domain mit {{ config('app.name') }} verbinden — DNS sauber konfigurieren.@endsection

@section('art_lede')
  In dieser Anleitung verbindest du eine Domain wie <code style="font-family:'Geist Mono',monospace;background:rgba(15,61,62,.06);padding:2px 8px;border-radius:6px;font-size:15px">deinname.de</code> mit deinem {{ config('app.name') }}-Auftritt. Wir gehen die DNS-Einträge bei den gängigen Providern durch und prüfen am Ende, ob das automatische SSL korrekt greift.
@endsection

@section('content')

  <p>Bevor du startest: Du brauchst Zugriff auf deinen <strong>{{ config('app.name') }}-Account</strong> und auf das <strong>DNS-Verwaltungs-Panel</strong> deines Domain-Providers (z.&nbsp;B. Strato, IONOS, GoDaddy). Die Umstellung dauert in der Regel 5–10 Minuten — die DNS-Verbreitung kann anschließend bis zu 24 Stunden dauern, ist meist aber innerhalb weniger Minuten abgeschlossen.</p>

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Du hast noch keine Domain?</h4>
      <p>Du kannst deine {{ config('app.name') }}-Seite zunächst über die kostenlose Sub-Domain betreiben und die eigene Domain später jederzeit nachreichen.</p>
    </div>
  </div>

  <h2 id="schritt-1">1. Domain im Dashboard hinzufügen</h2>
  <p>Öffne im Dashboard <strong>Einstellungen → Domains</strong> und klicke auf <em>Domain hinzufügen</em>. Trage deine Wunsch-Domain ein — z.&nbsp;B. <code>deinname.de</code> — und bestätige.</p>

  <h2 id="schritt-2">2. CNAME-Eintrag beim Provider hinterlegen</h2>
  <p>Logge dich beim Anbieter deiner Domain ein und öffne die DNS-Verwaltung. Lege folgenden Eintrag an:</p>

  <table>
    <thead>
      <tr><th>Typ</th><th>Host</th><th>Wert</th><th>TTL</th></tr>
    </thead>
    <tbody>
      <tr><td><code>CNAME</code></td><td><code>www</code></td><td><code>cname.wayvio.app</code></td><td>3600</td></tr>
    </tbody>
  </table>

  <p>Deine Seite ist damit unter <code>https://www.deinname.de</code> erreichbar.</p>

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Weiterleitung von der Root-Domain einrichten</h4>
      <p>Damit Besucher, die <code>deinname.de</code> (ohne www) eingeben, ebenfalls ankommen, richte zusätzlich eine <strong>URL-Weiterleitung</strong> von <code>deinname.de</code> auf <code>www.deinname.de</code> bei deinem Provider ein. Diese Option findest du je nach Anbieter unter „Weiterleitung", „Redirect" oder „URL-Forwarding".</p>
    </div>
  </div>

  <h2 id="schritt-3">3. Verbindung &amp; SSL prüfen</h2>
  <p>Zurück im Dashboard kannst du den Verbindungs-Check starten. {{ config('app.name') }} prüft, ob der CNAME-Eintrag korrekt gesetzt ist und stellt automatisch ein SSL-Zertifikat aus.</p>

  <ol>
    <li>Klicke im Dashboard neben deiner Domain auf <em>Verbindung prüfen</em>.</li>
    <li>Warte 1–10 Minuten — der Status wechselt von <code>Pending</code> auf <code>Connected</code>.</li>
    <li>Sobald der Status auf <code>Connected · SSL aktiv</code> steht, ist deine Seite über <code>https://www.deinname.de</code> erreichbar.</li>
  </ol>

  <h2 id="troubleshooting">Häufige Probleme</h2>

  <h3>Status bleibt auf <code>Pending</code></h3>
  <p>Die DNS-Verbreitung kann je nach Provider bis zu 24 Stunden dauern.</p>

  <h3>Domain zeigt auf alte Seite</h3>
  <p>Browser-Cache und CDN-Layer können alte Inhalte für kurze Zeit zwischenspeichern. Teste mit einem Incognito-Fenster und warte nach dem Wechsel ein paar Minuten.</p>

  <div class="callout">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
    <div>
      <h4>Funktioniert nach 24 h immer noch nicht?</h4>
      <p>Schreib uns mit der Domain und einem Screenshot deines DNS-Panels — wir schauen dann gemeinsam drauf.</p>
    </div>
  </div>

@endsection

@section('related')
  <a href="{{ route('help.faq') }}" class="rel">
    <span class="mini">Q&amp;A</span>
    <h4>DNS &amp; SSL — häufige Fragen</h4>
    <p>4 Min · Antworten</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
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
@endsection
