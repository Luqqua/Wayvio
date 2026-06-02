@extends('wayvio.layout')

@section('content')
    @push('wayvio-head')
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $privacy_title }} - {{ $userinfo->name }}</title>
        @include('wayvio.modules.favicon')
        @include('wayvio.modules.assets')
    @endpush

    @push('wayvio-head-end')
        @include('wayvio.modules.theme')
    @endpush

    @push('wayvio-content')
        @php
            $privacySections = [];
            $rawText = $privacy_text ?? '';
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawText));
            $currentSection = null;
            $paragraphLines = [];
            $bulletItems = [];
            $tableRows = [];

            $flushBuffers = function() use (&$currentSection, &$paragraphLines, &$bulletItems, &$tableRows) {
                if (!empty($paragraphLines) && $currentSection !== null) {
                    $currentSection['blocks'][] = ['type' => 'p', 'lines' => $paragraphLines];
                    $paragraphLines = [];
                }
                if (!empty($bulletItems) && $currentSection !== null) {
                    $currentSection['blocks'][] = ['type' => 'ul', 'items' => $bulletItems];
                    $bulletItems = [];
                }
                if (!empty($tableRows) && $currentSection !== null) {
                    $currentSection['blocks'][] = ['type' => 'table', 'rows' => $tableRows];
                    $tableRows = [];
                }
            };

            foreach ($lines as $line) {
                $trimmed = trim($line);
                $isSectionHeader = preg_match('/^\d+[a-z]?\.\s+\S/u', $trimmed);
                $isBullet = preg_match('/^[\t ]*[•●]\s*(.*)/u', $line, $bulletMatch);
                $isTableRow = preg_match('/^\|.+\|/', $trimmed);
                $isTableSep = $isTableRow && preg_match('/^\|[\s\-|]+\|$/', $trimmed);

                if ($isSectionHeader) {
                    $flushBuffers();
                    if ($currentSection !== null) {
                        $privacySections[] = $currentSection;
                    }
                    $currentSection = ['heading' => $trimmed, 'blocks' => []];
                } elseif ($currentSection === null) {
                    continue;
                } elseif ($isTableSep) {
                    // separator row — skip
                } elseif ($isTableRow) {
                    if (!empty($paragraphLines)) {
                        $currentSection['blocks'][] = ['type' => 'p', 'lines' => $paragraphLines];
                        $paragraphLines = [];
                    }
                    if (!empty($bulletItems)) {
                        $currentSection['blocks'][] = ['type' => 'ul', 'items' => $bulletItems];
                        $bulletItems = [];
                    }
                    $cells = array_map('trim', array_slice(explode('|', $trimmed), 1, -1));
                    $tableRows[] = $cells;
                } elseif ($isBullet) {
                    if (!empty($tableRows)) {
                        $currentSection['blocks'][] = ['type' => 'table', 'rows' => $tableRows];
                        $tableRows = [];
                    }
                    if (!empty($paragraphLines)) {
                        $currentSection['blocks'][] = ['type' => 'p', 'lines' => $paragraphLines];
                        $paragraphLines = [];
                    }
                    $bulletItems[] = trim($bulletMatch[1]);
                } elseif ($trimmed === '') {
                    $flushBuffers();
                } else {
                    if (!empty($tableRows)) {
                        $currentSection['blocks'][] = ['type' => 'table', 'rows' => $tableRows];
                        $tableRows = [];
                    }
                    if (!empty($bulletItems)) {
                        $currentSection['blocks'][] = ['type' => 'ul', 'items' => $bulletItems];
                        $bulletItems = [];
                    }
                    $paragraphLines[] = $trimmed;
                }
            }
            $flushBuffers();
            if ($currentSection !== null) {
                $privacySections[] = $currentSection;
            }
        @endphp

        <div class="privacy-shell">
            <h1 class="privacy-title">{{ $privacy_title }}</h1>

            @if(!empty($privacySections))
                @foreach($privacySections as $section)
                    <h2 class="privacy-section-title">{{ $section['heading'] }}</h2>
                    @foreach($section['blocks'] as $block)
                        @if($block['type'] === 'p')
                            <p class="privacy-body">{!! implode('<br>', array_map('e', $block['lines'])) !!}</p>
                        @elseif($block['type'] === 'ul')
                            <ul class="privacy-list">
                                @foreach($block['items'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @elseif($block['type'] === 'table')
                            <div class="privacy-table-wrap">
                                <table class="privacy-table">
                                    <thead>
                                        <tr>
                                            @foreach($block['rows'][0] as $cell)
                                                <th>{{ $cell }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(array_slice($block['rows'], 1) as $row)
                                            <tr>
                                                @foreach($row as $cell)
                                                    <td>{{ $cell }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endforeach
                    @if(!$loop->last)
                        <hr class="privacy-divider">
                    @endif
                @endforeach
            @else
                <div class="privacy-body">{!! nl2br(e($rawText)) !!}</div>
            @endif

            @php
                $domainResolver = app(\App\Services\Domains\DomainUrlResolver::class);
                $profileOwner = $domainResolver->ownerForPageUser($userinfo);
                $profileUrl = $domainResolver->profileUrlForEditor($profileOwner, $userinfo);
            @endphp
            <a class="privacy-back" href="{{ $profileUrl }}">{{ __('messages.imprint.back_to_page') }}</a>
        </div>

        <style>
            .privacy-shell {
                max-width: 640px;
                margin: 40px auto;
                padding: 2rem;
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 2px 20px rgba(0, 0, 0, 0.10);
                box-sizing: border-box;
                text-align: left;
            }

            .privacy-title {
                color: #111827;
                font-size: 1.5rem;
                font-weight: 700;
                margin: 0 0 2rem;
            }

            .privacy-section-title {
                color: #111827;
                font-size: 1rem;
                font-weight: 600;
                margin: 0 0 0.75rem;
            }

            .privacy-body {
                color: #111827;
                font-size: 0.9375rem;
                line-height: 1.65;
                font-weight: 400;
                word-break: break-word;
                margin: 0 0 0.75rem;
            }

            .privacy-body:last-child {
                margin-bottom: 0;
            }

            .privacy-list {
                color: #111827;
                font-size: 0.9375rem;
                line-height: 1.6;
                padding-left: 1.25rem;
                margin: 0 0 0.75rem;
                list-style: disc;
            }

            .privacy-list:last-child {
                margin-bottom: 0;
            }

            .privacy-divider {
                border: 0;
                border-top: 1px solid #e5e7eb;
                margin: 1.5rem 0;
            }

            .privacy-table-wrap {
                overflow-x: auto;
                margin: 0 0 0.75rem;
                border-radius: 8px;
                border: 1px solid #e5e7eb;
            }

            .privacy-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.875rem;
                color: #111827;
                line-height: 1.5;
            }

            .privacy-table th,
            .privacy-table td {
                padding: 0.5rem 0.75rem;
                text-align: left;
                border-bottom: 1px solid #e5e7eb;
                vertical-align: top;
            }

            .privacy-table th {
                background: #f9fafb;
                font-weight: 600;
                white-space: nowrap;
            }

            .privacy-table tbody tr:last-child td {
                border-bottom: 0;
            }

            .privacy-table tbody tr:hover {
                background: #f9fafb;
            }

            .privacy-back {
                display: inline-block;
                margin-top: 2rem;
                padding: 0;
                border: 0;
                color: #6b7280;
                text-decoration: none;
                font-size: 0.875rem;
                line-height: 1.5;
            }

            .privacy-back:hover {
                text-decoration: underline;
            }

            .privacy-back:focus {
                outline: 2px solid rgba(0, 0, 0, 0.35);
                outline-offset: 2px;
            }

            @media (max-width: 640px) {
                .privacy-shell {
                    margin: 24px 16px;
                    padding: 1.25rem 1rem;
                    border-radius: 12px;
                }
            }
        </style>

        @include('wayvio.modules.footer', ['info' => $information->first()])
    @endpush
@endsection
