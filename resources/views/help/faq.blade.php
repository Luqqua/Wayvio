@extends('help.article-layout')

@section('meta_title', 'Q&A — Häufige Fragen | ' . config('app.name'))
@section('meta_description', 'Antworten auf die häufigsten Fragen zu Setup, Embed-Blöcken, SEO und Agentur-Workflows.')

@php
  $faqGroups = [
      [
          'title' => 'Setup und Struktur',
          'items' => [
              [
                  'q' => 'Wie starte ich einen Hub ohne wichtige Schritte zu verpassen?',
                  'a' => 'Starte mit dem Schnellstart-Guide für deine Rolle. Definiere ein primäres Conversion-Ziel (z.&nbsp;B. Buchung oder Kontakt) — das leitet alle weiteren Entscheidungen. Baue zuerst die Kernblöcke (Intro, Angebot, Vertrauen, CTA) und ergänze optionale Elemente in einem zweiten Durchgang.',
              ],
              [
                  'q' => 'Welche Block-Reihenfolge wird empfohlen?',
                  'a' => 'Eine bewährte Struktur ist: Hero/Intro, Kernangebot, Vertrauenselemente, primärer CTA, dann sekundäre Details und Links. Diese Reihenfolge hilft sowohl Besuchern als auch Suchmaschinen, den Fokus der Seite schnell zu verstehen.',
              ],
              [
                  'q' => 'Wann ist ein eigener Hub besser als nur Social-Media-Profile?',
                  'a' => 'Sobald du mehrere Conversion-Pfade, Buchungs- oder Kontaktformulare brauchst oder messbare Kampagnen schalten möchtest. Ein strukturierter Hub ist langfristig einfacher zu optimieren und anzupassen — ohne aufwendige Website-Programmierung.',
              ],
              [
                  'q' => 'Wie nutze ich vorgefertigte Templates am besten?',
                  'a' => 'Wähl ein Template das deiner Branche oder deinem Ziel am nächsten kommt. Ersetze dann die Platzhalter-Inhalte durch deine eigenen Texte, Bilder und Links. Der Block-Stapel-Mechanismus erlaubt es dir, Blöcke nach Bedarf umzusortieren oder einzelne zu entfernen.',
              ],
          ],
      ],
      [
          'title' => 'Embed-Blöcke',
          'items' => [
              [
                  'q' => 'Wann sollte ich einen Embed-Block verwenden?',
                  'a' => 'Nutze Embeds, wenn ein Tool nur per iFrame oder externem Script eingebunden werden kann (z.&nbsp;B. Buchungswidgets, Maps oder Drittanbieter-Formulare). Native Blöcke sind in der Regel schneller für einfache Inhalte.',
              ],
              [
                  'q' => 'Wie verhindere ich, dass Embeds die Performance beeinträchtigen?',
                  'a' => 'Halte nur essentielle Embeds aktiv. Platziere schwerere Module weiter unten auf der Seite. Prüfe die mobile Performance nach jeder Änderung. Zu viele gleichzeitige Embeds können den First Contentful Paint deutlich verzögern.',
              ],
          ],
      ],
      [
          'title' => 'SEO und Sichtbarkeit',
          'items' => [
              [
                  'q' => 'Wie optimiere ich meine Seite für Suchmaschinen?',
                  'a' => 'Setze einen aussagekräftigen Title-Tag (max. 60 Zeichen) und eine Meta-Description (max. 155 Zeichen). Nutze klare H1/H2-Struktur in deinen Block-Inhalten. Hinterlege ein Open-Graph-Bild für Social-Media-Vorschauen. ' . config('app.name') . ' unterstützt alle technischen SEO-Grundlagen out of the box.',
              ],
              [
                  'q' => 'Wird meine Seite automatisch von Google indexiert?',
                  'a' => 'Sobald deine Seite veröffentlicht und eine Domain verbunden ist, ist sie für Suchmaschinen erreichbar. Du kannst in den Einstellungen eine Sitemap aktivieren und robots.txt anpassen, um die Indexierung zu steuern.',
              ],
          ],
      ],
      [
          'title' => 'Agentur-Workflows',
          'items' => [
              [
                  'q' => 'Wie skaliere ich die Hub-Bereitstellung für mehrere Kunden?',
                  'a' => 'Nutze standardisierte Templates, einheitliche Namenskonventionen und eine QA-Checkliste für jede Übergabe. Ein konsistenter Rollout-Prozess reduziert Domain-, Content- und SEO-Fehler auf ein Minimum.',
              ],
          ],
      ],
  ];

  $faqSchemaItems = [];
  foreach ($faqGroups as $group) {
      foreach ($group['items'] as $item) {
          $faqSchemaItems[] = [
              '@type' => 'Question',
              'name' => strip_tags($item['q']),
              'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($item['a'])],
          ];
      }
  }
@endphp

@push('help-head')
  <script type="application/ld+json">
    {!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqSchemaItems], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
  </script>
@endpush

@section('breadcrumb_trail')
  <span class="here">Q&amp;A</span>
@endsection



@section('art_title')Fragen und Antworten zum Hub@endsection

@section('art_lede')
  Diese FAQ deckt Setup, Embed-Blöcke, SEO und Agentur-Workflows — mit direkten, praxisnahen Antworten ohne Umwege.
@endsection

@section('content')

  @foreach($faqGroups as $group)
    <h2>{{ $group['title'] }}</h2>
    @foreach($group['items'] as $item)
      <h3>{{ $item['q'] }}</h3>
      <p>{!! $item['a'] !!}</p>
    @endforeach
  @endforeach

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
  <a href="{{ route('help.embed-blocks') }}" class="rel">
    <span class="mini">Embeds &amp; Formulare</span>
    <h4>Embeds richtig einbinden</h4>
    <p>5 Min · Leitfaden</p>
    <span class="arr">Lesen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
  </a>
@endsection
