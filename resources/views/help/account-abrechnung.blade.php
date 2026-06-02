@extends('help.article-layout')

@section('meta_title', 'Account & Abrechnung — Plan, Rechnungen, 2FA & Kündigung | ' . config('app.name'))
@section('meta_description', 'Plan upgraden oder downgraden, Zahlungen einsehen, Zwei-Faktor-Authentifizierung aktivieren und Konto kündigen.')

@section('breadcrumb_trail')
  <span class="here">Account &amp; Abrechnung</span>
@endsection



@section('art_title')Account & Abrechnung — Plan, Rechnungen, Sicherheit und Kündigung.@endsection

@section('art_lede')
  Alles rund um deinen {{ config('app.name') }}-Account: Plan upgraden oder downgraden, Zahlungen einsehen, Zwei-Faktor-Authentifizierung einrichten und das Konto bei Bedarf kündigen.
@endsection

@section('content')

  <h2 id="plan-wechseln">Plan upgraden oder downgraden</h2>
  <p>Deinen Plan verwaltest du unter <strong>Einstellungen → Abonnement</strong>. Dort siehst du deinen aktuellen Plan, alle verfügbaren Optionen und den nächsten Abrechnungszeitpunkt.</p>

  <h3>Upgrade</h3>
  <p>Ein Upgrade wird sofort wirksam. Du zahlst anteilig (pro-rata) für die restlichen Tage des laufenden Abrechnungszeitraums — die Differenz wird automatisch von deiner hinterlegten Zahlungsmethode abgezogen.</p>

  <h3>Downgrade</h3>
  <p>Ein Downgrade wird zum Ende des aktuellen Abrechnungszeitraums wirksam. Bis dahin behältst du alle Features deines aktuellen Plans. Stelle sicher, dass deine Seite nach dem Downgrade noch im neuen Plan-Umfang liegt — z.&nbsp;B. hinsichtlich Custom Domains oder Embed-Limits. Inhalte wie Formulareinsendungen oder Analytics-Daten, die das Kontingent des neuen Plans überschreiten, sind nach dem Downgrade nicht mehr einsehbar. Speichere solche Daten daher vorher lokal — Formulareinsendungen werden nach 30 Tagen gelöscht.</p>

  <h2 id="rechnungen">Zahlungen &amp; Rechnungen</h2>
  <p>Deine Zahlungen und Rechnungen kannst du unter <strong>Abonnement → Zahlung &amp; Rechnung</strong> einsehen und als PDF herunterladen.</p>

  <h2 id="2fa">Zwei-Faktor-Authentifizierung (2FA)</h2>
  <p>Zwei-Faktor-Authentifizierung schützt deinen Account, selbst wenn dein Passwort kompromittiert wurde. Sie lässt sich direkt unter <strong>Einstellungen</strong> aktivieren und deaktivieren.</p>

  <div class="callout warn">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div>
      <h4>Backup-Codes sicher aufbewahren</h4>
      <p>Verlierst du dein Gerät und hast keine Backup-Codes, verlierst du möglicherweise den Zugang zu deinem Account. Speichere die Codes in einem Passwort-Manager oder drucke sie aus und bewahre sie sicher auf.</p>
    </div>
  </div>

  <h2 id="kuendigung">Konto kündigen</h2>
  <p>Das Konto kannst du unter <strong>Einstellungen → Abonnement → Kündigen</strong> kündigen. Die Kündigung wird zum Ende des aktuellen Abrechnungszeitraums wirksam — bis dahin hast du weiterhin vollen Zugriff.</p>

  <div class="callout">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
    <div>
      <h4>Fragen zur Rechnung oder Abrechnung?</h4>
      <p>Schreib uns an <a href="mailto:support@example.com">support@example.com</a> — wir klären Rechnungsfragen in der Regel innerhalb eines Werktags.</p>
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
  <a href="{{ route('help.quickstart.agency') }}" class="rel">
    <span class="mini">Agentur &amp; White-Label</span>
    <h4>Schnellstart für Agenturen</h4>
    <p>8 Min · Schritt-für-Schritt</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.faq') }}" class="rel">
    <span class="mini">Q&amp;A</span>
    <h4>Häufige Fragen &amp; Antworten</h4>
    <p>Alle Themen</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
