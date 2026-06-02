@extends('help.article-layout')

@section('meta_title', 'Vorgeschlagener Seitenaufbau — die optimale Block-Reihenfolge | ' . config('app.name'))
@section('meta_description', 'Welche Blöcke in welcher Reihenfolge wirken: der empfohlene Seitenaufbau für lokale Unternehmen, Kreative, Gastro und Link-in-Bio-Seiten.')

@section('breadcrumb_trail')
  <span class="here">Seitenaufbau</span>
@endsection



@section('art_title')Vorgeschlagener Seitenaufbau — die optimale Block-Reihenfolge für deinen Hub.@endsection

@section('art_lede')
  Die Reihenfolge deiner Blöcke beeinflusst, ob Besucher bleiben oder abspringen. Dieser Leitfaden zeigt dir bewährte Strukturen für verschiedene Ziele — von lokalen Unternehmen bis zur Link-in-Bio-Seite.
@endsection

@section('content')

  <h2 id="universell">Die universelle Basisstruktur</h2>
  <p>Diese Reihenfolge funktioniert für die meisten Ziele und Branchen als solider Ausgangspunkt:</p>

  <ol>
    <li>
      <strong>Hero / Profil-Block</strong> — Wer bist du, was bietest du an? In einem Satz. Mit Profilbild oder Logo.
    </li>
    <li>
      <strong>Primärer CTA</strong> — Die wichtigste Aktion so weit oben wie möglich: Buchung, Kontaktaufnahme, Kauf. Kein Scrollen nötig.
    </li>
    <li>
      <strong>Kernangebot</strong> — 2–4 konkrete Leistungen oder Produkte mit kurzem Nutzen-Text.
    </li>
    <li>
      <strong>Vertrauenselemente</strong> — Bewertungen, Referenzen, Logos, Auszeichnungen. Beweise statt Behauptungen.
    </li>
    <li>
      <strong>Sekundäre Links</strong> — Social Media, Portfolio, weitere Seiten. Kein Ablenkungspotenzial oben, aber zugänglich.
    </li>
  </ol>

  <div class="callout tip">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
    <div>
      <h4>Der erste sichtbare Bereich entscheidet</h4>
      <p>Was Besucher ohne Scrollen sehen (Above the Fold), bestimmt ob sie bleiben. Der primäre CTA muss hier sichtbar sein — ohne Ausnahme.</p>
    </div>
  </div>

  <h2 id="lokales-unternehmen">Aufbau für lokale Unternehmen</h2>
  <p>Für Friseure, Restaurants, Handwerker, Ärzte, Coaches und ähnliche lokale Dienstleister:</p>
  <ol>
    <li>Profil-Block (Name, Kurzbeschreibung, Foto)</li>
    <li>CTA-Block „Jetzt Termin buchen" oder „Anrufen" — ganz oben</li>
    <li>Leistungen-Block (3–5 Leistungen kurz beschrieben)</li>
    <li>Bewertungs-Block (Google-Bewertungen oder manuell eingetragen)</li>
    <li>Öffnungszeiten-Block</li>
    <li>Adress-Block mit Maps-Einbettung</li>
    <li>Kontaktformular-Block als Fallback</li>
    <li>Social-Links-Block ganz unten</li>
  </ol>

  <h2 id="kreative">Aufbau für Kreative & Freelancer</h2>
  <p>Für Fotografen, Designer, Texter, Musiker und andere Kreative:</p>
  <ol>
    <li>Profil-Block (Name + Tagline, die dein Alleinstellungsmerkmal in 5 Worten beschreibt)</li>
    <li>Galerie-Block oder Featured-Projekt</li>
    <li>CTA „Projektanfrage stellen" oder „Portfolio ansehen"</li>
    <li>Zitat-Block (Testimonial eines Kunden)</li>
    <li>Link-Block zu Portfolio-Website oder Behance/Dribbble</li>
    <li>Social-Links</li>
    <li>Kontaktformular</li>
  </ol>

  <h2 id="link-in-bio">Aufbau für Link-in-Bio-Seiten</h2>
  <p>Für Creator, Influencer oder alle, die ihre Bio-Links konsolidieren wollen:</p>
  <ol>
    <li>Profil-Block (großes Profilbild, Name, kurze Bio)</li>
    <li>Wichtigster Link als CTA-Block (z.&nbsp;B. neuster YouTube-Upload, aktueller Drop)</li>
    <li>3–5 Link-Blöcke nach Relevanz sortiert</li>
    <li>Social-Links-Block</li>
    <li>Optionaler Newsletter-Signup-Block</li>
  </ol>

  <div class="callout warn">
    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div>
      <h4>Maximal 8–10 Blöcke für den Start</h4>
      <p>Jeder zusätzliche Block verdünnt die Aufmerksamkeit. Starte mit dem Minimum und füge nur dann hinzu, wenn Analytics zeigt, dass Besucher etwas Bestimmtes suchen.</p>
    </div>
  </div>

  <h2 id="header-position">Wann Header-Block nach oben, wann nach unten?</h2>
  <p>Ein Text-Überschriften-Block (reiner Header ohne Profil) macht Sinn als:</p>
  <ul>
    <li><strong>Trennlinie</strong> zwischen inhaltlichen Abschnitten (z.&nbsp;B. „Meine Leistungen" vor den Leistungs-Blöcken)</li>
    <li><strong>Abschnitts-Einleitung</strong> weiter unten auf der Seite</li>
  </ul>
  <p>Ein Header-Block ganz oben (statt eines Profil-Blocks) lohnt sich nur, wenn deine Marke oder dein Angebot so bekannt ist, dass der Name allein reicht — für die meisten Nutzer ist ein Profil-Block mit Bild oben stärker.</p>

@endsection

@section('related')
  <a href="{{ route('help.block-editor') }}" class="rel">
    <span class="mini">Block-Stapel</span>
    <h4>Alle Block-Typen im Überblick</h4>
    <p>6 Min · Referenz</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.header-modi') }}" class="rel">
    <span class="mini">Block-Stapel</span>
    <h4>Header-Modi verstehen</h4>
    <p>4 Min · Anleitung</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
  <a href="{{ route('help.analytics') }}" class="rel">
    <span class="mini">Analytics</span>
    <h4>Analytics verstehen</h4>
    <p>5 Min · Leitfaden</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
