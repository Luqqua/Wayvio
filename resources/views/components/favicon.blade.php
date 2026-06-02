<?php
use App\Models\Link;

if (!function_exists('getFavIcon')) {
    function getFavIcon($id)
    {
        $normalizedId = (int) $id;
        if ($normalizedId <= 0) {
            return asset('assets/wayvio/icons/website.svg');
        }

        try {
            $link = Link::find($normalizedId);
            if (!$link || !is_string($link->link) || trim($link->link) === '') {
                return asset('assets/wayvio/icons/website.svg');
            }

            $rawUrl = trim($link->link);
            $host = parse_url($rawUrl, PHP_URL_HOST);
            $domain = is_string($host) && $host !== '' ? $host : $rawUrl;

            // Use Google's Favicon API
            $faviconUrl = 'https://www.google.com/s2/favicons?sz=256&domain=' . rawurlencode($domain);

            // Get the favicon and save it to the desired location
            $favicon = file_get_contents($faviconUrl);
            if ($favicon === false) {
                throw new \RuntimeException('Unable to fetch favicon');
            }

            $filename = $normalizedId . '.png';
            $filepath = base_path('assets/favicon/icons') . '/' . $filename;
            file_put_contents($filepath, $favicon);

            return url('assets/favicon/icons/' . $filename);
        } catch (\Throwable $e) {
            // Handle the exception by copying the default SVG favicon
            $defaultIcon = base_path('assets/wayvio/icons/website.svg');
            $filename = $normalizedId . '.svg';
            $filepath = base_path('assets/favicon/icons') . '/' . $filename;
            if (is_file($defaultIcon)) {
                copy($defaultIcon, $filepath);
            }

            return url('assets/favicon/icons/' . $filename);
        }
    }
}
