@extends('help.article-layout')

@section('meta_title', 'Kopfbereich-Layout — Link in Bio, Business & Business Header | ' . config('app.name'))
@section('meta_description', 'Die drei Layout-Optionen für den Kopfbereich: Link in Bio (Fokus Logo), Business (Fokus Titel) und Business Header (Fokus Beschreibung) — was sich jeweils ändert.')

@section('breadcrumb_trail')
  <span class="here">Header-Modi</span>
@endsection



@section('art_title')Kopfbereich-Layout — Logo, Titel oder Beschreibung im Fokus.@endsection

@section('art_lede')
  {{ config('app.name') }} bietet drei Layout-Optionen für den Kopfbereich, die bestimmen, was visuell im Vordergrund steht und wo die Social-Icons erscheinen. Du findest die Einstellung unter <strong>Studio → Kopfbereich</strong> im Abschnitt <em>Anordnung von Logo, Titel &amp; Beschreibung</em>.
@endsection

@section('content')

  <h2 id="link-in-bio">Link in Bio (Fokus Logo)</h2>
  <p>Das Standard-Layout. Das Logo steht visuell im Mittelpunkt des Kopfbereichs. Social-Media-Icons erscheinen direkt im Kopfbereich neben Name und Beschreibung.</p>
  <h3>Wann einsetzen</h3>
  <ul>
    <li>Klassische Link-in-Bio-Struktur, bei der das Profilbild oder Logo der Anker ist</li>
    <li>Creator, Personal Brands und Social-First-Seiten</li>
    <li>Social-Icons sollen prominent ganz oben direkt sichtbar sein</li>
  </ul>

  <h2 id="business-fokus-titel">Business (Fokus Titel)</h2>
  <p>Titel und Name rücken in den Vordergrund. Das Layout ist auf Business-Auftritte ausgerichtet, bei denen der Marken- oder Unternehmensname sofort lesbar sein soll. Social-Icons erscheinen im Footer. Der separate Header-Banner ist in diesem Layout nicht verfügbar — nur das Hero-Bild kann zusätzlich zugeschaltet werden.</p>
  <h3>Wann einsetzen</h3>
  <ul>
    <li>Lokale Unternehmen, Dienstleister und Firmen mit bekanntem Namen</li>
    <li>Du willst einen klaren, professionellen ersten Eindruck mit dem Namen im Fokus</li>
    <li>Social-Links sind zweitrangig — Footer-Platzierung ist ausreichend</li>
  </ul>

  <h2 id="business-fokus-beschreibung">Business Header (Fokus Beschreibung)</h2>
  <p>Die Beschreibung steht klar im Vordergrund — Name und Logo sind vorhanden, aber deutlich kleiner und zurückgenommen. Ideal wenn ein neuer Besucher sofort verstehen soll, was du anbietest. Social-Icons erscheinen im Footer. Auch hier ist der separate Header-Banner nicht verfügbar; das Hero-Bild kann optional zugeschaltet werden.</p>
  <h3>Wann einsetzen</h3>
  <ul>
    <li>Unternehmen oder Freelancer, deren Zielgruppe über Suche oder Werbung kommt und sie noch nicht kennt</li>
    <li>Die Beschreibung ist entscheidend für die Conversion</li>
    <li>Du willst mehr erklärender Text direkt im Kopfbereich</li>
  </ul>
  <p>Dies ist die <strong>empfohlene Ausgangswahl</strong> für die meisten Nutzer ohne eine bekannte Marke.</p>

  <h2 id="vergleich">Schnellvergleich</h2>
  <table>
    <thead>
      <tr><th>Layout</th><th>Fokus</th><th>Social-Icons</th></tr>
    </thead>
    <tbody>
      <tr><td>Link in Bio</td><td>Logo</td><td>Kopfbereich</td></tr>
      <tr><td>Business</td><td>Titel / Name</td><td>Footer</td></tr>
      <tr><td>Business Header</td><td>Titel + Beschreibung</td><td>Footer</td></tr>
    </tbody>
  </table>

@endsection

@section('related')
  <a href="{{ route('help.seitenaufbau') }}" class="rel">
    <span class="mini">Erste Schritte</span>
    <h4>Vorgeschlagener Seitenaufbau</h4>
    <p>5 Min · Leitfaden</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.block-editor') }}" class="rel">
    <span class="mini">Block-Stapel</span>
    <h4>Alle Block-Typen im Überblick</h4>
    <p>6 Min · Referenz</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.quickstart.user') }}" class="rel">
    <span class="mini">Erste Schritte</span>
    <h4>Schnellstart für Nutzer</h4>
    <p>6 Min · Schritt-für-Schritt</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
