@extends('help.article-layout')

@section('meta_title', 'Agentur-Account — Hubs zentral verwalten | ' . config('app.name'))
@section('meta_description', 'Warum ein Agentur-Account? Hubs anlegen, zwischen Kunden wechseln, eigene Domain, Analytics und Meta-Daten pro Hub.')

@section('breadcrumb_trail')
  <span class="here">Agentur &amp; White-Label</span>
@endsection



@section('art_title')Agentur-Account — mehrere Kunden-Hubs zentral verwalten.@endsection

@section('art_lede')
  Mit einem Agentur-Account verwaltest du beliebig viele Kunden-Hubs aus einer einzigen Oberfläche. Jeder Hub ist vollständig isoliert — eigene Domain, eigene Analytics, eigene Meta-Daten — und lässt sich mit wenigen Klicks wechseln.
@endsection

@section('content')

  <h2 id="warum-agentur">Warum ein Agentur-Account?</h2>
  <p>Ein Standard-Account ist für einen einzelnen Hub gedacht. Sobald du mehrere Kunden betreust, wird der Agentur-Account relevant: Du erhältst eine zentrale Hub-Verwaltung, einen schnellen Workspace-Wechsel direkt in der Sidebar und White-Label-Optionen für dein eigenes Branding — alles ohne separate Logins pro Kunde.</p>

  <h2 id="hubs-verwalten">Hubs anlegen und verwalten</h2>
  <p>Alle deine Kunden-Hubs findest du unter <strong>Hub-Verwaltung</strong>. Dort siehst du auf einen Blick, wie viele deiner gebuchten Slots belegt sind, und kannst neue Hubs anlegen oder bestehende verwalten.</p>
  <p>Beim Anlegen eines neuen Hubs vergibst du einen <strong>Anzeigenamen</strong> und eine <strong>URL-Endung</strong> — aus der URL-Endung entsteht die Standard-Adresse des Hubs (<code>wayvio.de/deine-endung</code>). Sobald eine eigene Domain verbunden ist, wird diese stattdessen verwendet.</p>
  <p>Jeder Hub kann einzeln <strong>öffentlich oder privat</strong> geschaltet, <strong>deaktiviert</strong> (Slot bleibt belegt, Hub ist nicht erreichbar) oder bei Bedarf <strong>endgültig gelöscht</strong> werden.</p>

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Slots</h4>
      <p>Dein Plan legt fest, wie viele Hubs du gleichzeitig betreiben kannst. Deaktivierte Hubs belegen weiterhin einen Slot — erst nach endgültigem Löschen wird der Slot freigegeben.</p>
    </div>
  </div>

  <h2 id="workspace-wechsel">Zwischen Hubs wechseln</h2>
  <p>In der Sidebar findest du unter <strong>Hub Selector</strong> ein Dropdown mit allen deinen Hubs. Ein Klick wechselt den aktiven Workspace — alle Einstellungen, der Block-Editor und die Vorschau beziehen sich dann auf diesen Hub. Der aktuell aktive Hub wird oben im Hub Selector angezeigt.</p>

  <h2 id="domain-pro-hub">Eigene Domain pro Hub</h2>
  <p>Jeder Hub kann mit einer eigenen Custom Domain verbunden werden. Die Domain wird im Hub-Kontext unter <strong>Einstellungen → Domain</strong> eingetragen — unabhängig von allen anderen Hubs. So kann jeder Kunde unter seiner eigenen Domain erreichbar sein. Alternativ lässt sich über <a href="{{ route('help.white-label') }}">White-Label</a> eine globale Agentur-Domain einrichten, unter der dann alle Hubs erreichbar sind — statt der Standard-Domain wayvio.de.</p>

  <h2 id="analytics-meta">Analytics und Meta-Daten pro Hub</h2>
  <p>Analytics und Meta-Daten sind immer hubspezifisch. Wechselst du den aktiven Workspace, siehst du ausschließlich die Daten des jeweiligen Hubs — Seitenaufrufe, Klicks und Conversion-Daten sind nicht hub-übergreifend vermischt. Ebenso sind Meta-Daten wie Seitentitel, Beschreibung und Open-Graph-Felder pro Hub individuell einstellbar.</p>

@endsection

@section('related')
  <a href="{{ route('help.quickstart.user') }}" class="rel">
    <span class="mini">Erste Schritte</span>
    <h4>Schnellstart für Nutzer</h4>
    <p>6 Min · Schritt-für-Schritt</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.white-label') }}" class="rel">
    <span class="mini">Agentur &amp; White-Label</span>
    <h4>White-Label einrichten</h4>
    <p>5 Min · Leitfaden</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.domains') }}" class="rel">
    <span class="mini">Domains &amp; SSL</span>
    <h4>Domain verbinden — SSL wird automatisch eingerichtet</h4>
    <p>4 Min · Anleitung</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
