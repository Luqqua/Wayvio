@php
    $rawUrl = is_string($link->link ?? null) ? trim($link->link) : '';
    $embedUrl = '';

    if ($rawUrl !== '') {
        if (!preg_match('/^https?:\/\//i', $rawUrl)) {
            $rawUrl = 'https://' . ltrim($rawUrl, '/');
        }

        $parts = parse_url($rawUrl);
        if (is_array($parts)) {
            $host = strtolower((string) ($parts['host'] ?? ''));
            $path = trim((string) ($parts['path'] ?? ''), '/');
            $query = [];
            parse_str((string) ($parts['query'] ?? ''), $query);
            $segments = array_values(array_filter(explode('/', $path), 'strlen'));

            if ($host === 'youtu.be' || str_ends_with($host, '.youtu.be')) {
                $videoId = (string) ($segments[0] ?? '');
                if (preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1) {
                    $embedUrl = 'https://www.youtube-nocookie.com/embed/' . $videoId;
                }
            } elseif ($host === 'youtube.com' || str_ends_with($host, '.youtube.com')) {
                $videoId = '';
                if (($segments[0] ?? '') === 'watch') {
                    $videoId = (string) ($query['v'] ?? '');
                } elseif (in_array((string) ($segments[0] ?? ''), ['embed', 'shorts', 'live'], true)) {
                    $videoId = (string) ($segments[1] ?? '');
                }

                if (preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1) {
                    $embedUrl = 'https://www.youtube-nocookie.com/embed/' . $videoId;
                }
            } elseif ($host === 'vimeo.com' || str_ends_with($host, '.vimeo.com')) {
                for ($i = count($segments) - 1; $i >= 0; $i--) {
                    if (preg_match('/^\d{6,14}$/', (string) $segments[$i]) === 1) {
                        $embedUrl = 'https://player.vimeo.com/video/' . $segments[$i];
                        break;
                    }
                }
            }
        }
    }
@endphp

<div class='button-video'>
    @if($embedUrl !== '')
        <iframe
            src="{{ $embedUrl }}"
            loading="lazy"
            referrerpolicy="strict-origin-when-cross-origin"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen"
            allowfullscreen
            style="width: 100%; aspect-ratio: 16 / 9; border: 0;"
        ></iframe>
    @endif
</div>
