<?php

function env($key, $default = null) {
    return $default;
}

$catalog = include __DIR__ . '/../config/template-catalog.php';
$themesRoot = realpath(__DIR__ . '/../themes');
$templateIds = [
    'dunes', 'edge', 'riso', 'dots', 'halftone', 'linen', 'sunburst', 'topo',
    'cross', 'op', 'confetti', 'marble', 'stripe', 'check', 'cloud', 'wabi-paper',
];

function normalize_hex($value) {
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if (!preg_match('/^#?([0-9a-fA-F]{6})$/', $value, $matches)) {
        return null;
    }
    return '#' . strtoupper($matches[1]);
}

function rgb($hex) {
    $hex = ltrim($hex, '#');
    return [
        hexdec(substr($hex, 0, 2)) / 255,
        hexdec(substr($hex, 2, 2)) / 255,
        hexdec(substr($hex, 4, 2)) / 255,
    ];
}

function linear($channel) {
    return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
}

function luminance($hex) {
    [$r, $g, $b] = rgb($hex);
    return 0.2126 * linear($r) + 0.7152 * linear($g) + 0.0722 * linear($b);
}

function contrast_ratio($a, $b) {
    $l1 = luminance($a);
    $l2 = luminance($b);
    $light = max($l1, $l2);
    $dark = min($l1, $l2);
    return ($light + 0.05) / ($dark + 0.05);
}

function theme_background($themesRoot, $theme) {
    $path = $themesRoot . '/' . $theme . '/skeleton-auto.css';
    if (!is_file($path)) {
        return null;
    }
    $css = file_get_contents($path);
    if (preg_match('/--background-color:\s*(#[0-9a-fA-F]{6})\s*;/', $css, $matches)) {
        return normalize_hex($matches[1]);
    }
    return null;
}

$errors = [];

foreach ($templateIds as $templateId) {
    $template = $catalog['templates'][$templateId] ?? null;
    if (!is_array($template)) {
        $errors[] = "{$templateId}: missing catalog entry";
        continue;
    }

    $defaults = $template['design_defaults'] ?? [];
    $theme = $template['theme'] ?? '';
    $background = theme_background($themesRoot, $theme);
    $style = $defaults['button_style'] ?? null;
    $text = normalize_hex($defaults['text_accent_color'] ?? null);
    $buttonText = normalize_hex($defaults['button_text_color'] ?? null);
    $buttonBorder = normalize_hex($defaults['button_border_color'] ?? null);
    $buttonBackground = $defaults['button_background_color'] ?? null;

    if (!in_array($style, ['solid', 'outline'], true)) {
        $errors[] = "{$templateId}: button_style must be solid or outline";
    }
    if (!$text || !$buttonText || !$buttonBorder) {
        $errors[] = "{$templateId}: text/button/border colors must be valid 6-digit hex values";
        continue;
    }
    if ($text !== $buttonText) {
        $errors[] = "{$templateId}: text_accent_color and button_text_color must match";
    }
    if ($style === 'outline' && $buttonBorder !== $buttonText) {
        $errors[] = "{$templateId}: outline border and text colors must match";
    }
    if ($style === 'outline' && $buttonBackground !== 'transparent') {
        $errors[] = "{$templateId}: outline background must be transparent";
    }
    if ($style === 'solid') {
        $buttonBackgroundHex = normalize_hex($buttonBackground);
        if (!$buttonBackgroundHex) {
            $errors[] = "{$templateId}: solid background must be a valid hex color";
        } elseif (contrast_ratio($buttonText, $buttonBackgroundHex) < 4.5) {
            $errors[] = "{$templateId}: solid button text contrast is below WCAG AA";
        }
    }
    if ($background && contrast_ratio($text, $background) < 4.5) {
        $errors[] = "{$templateId}: text/accent contrast against declared background {$background} is below WCAG AA";
    }
}

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo 'Template design defaults audit passed for ' . count($templateIds) . " templates.\n";
