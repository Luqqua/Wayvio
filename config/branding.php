<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Öffentliche Profile (Wayvio-Seiten)
    |--------------------------------------------------------------------------
    |
    | Optionale globale Vorgaben für die öffentlichen Wayvio-Seiten.
    | lock_customizations ist nicht mehr aktiv – User behalten immer die Kontrolle
    | über ihre öffentlichen Seiten. Dieser Schalter bleibt nur als Legacy-Eintrag.
    |
    */
    'public' => [
        'lock_customizations' => false, // Legacy: wird nicht mehr ausgewertet

        // Erzwinge ein Theme aus /themes (z. B. "galaxy" oder "default")
        'theme' => env('BRANDING_PUBLIC_THEME', 'default'),

        // Globaler Hintergrund / Hero
        'background' => [
            // color | image | template
            'mode' => env('BRANDING_BACKGROUND_MODE', 'color'),
            // Nur relevant für mode=color
            'color' => env('BRANDING_BACKGROUND_COLOR', '#111827'),
            // Nur relevant für mode=image. Relativer Pfad ab Projekt-Root (z.B. assets/img/background-img/global.jpg)
            // oder absolute URL.
            'image' => env('BRANDING_BACKGROUND_IMAGE', null),

            'overlay_color' => env('BRANDING_BACKGROUND_OVERLAY_COLOR', '#000000'),
            // 0–100
            'overlay_opacity' => (int) env('BRANDING_BACKGROUND_OVERLAY_OPACITY', 50),

            // Optionaler Verlauf für mode=color
            'gradient_enabled' => (bool) env('BRANDING_BACKGROUND_GRADIENT', false),
            'gradient_stops' => [
                // ['color' => '#0ea5e9', 'position' => 0],
                // ['color' => '#2563eb', 'position' => 100],
            ],
        ],

        // Hero/Header-Bild oben auf dem Profil (nur Themes, die Header unterstützen)
        'header' => [
            'enabled' => false,
            // Relativer Pfad ab Projekt-Root oder absolute URL
            'image' => env('BRANDING_HEADER_IMAGE', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard / Admin
    |--------------------------------------------------------------------------
    |
    | Sidebar-Look & Dashboard-Hero global fest verdrahten. UI-Schalter werden entfernt.
    |
    */
    'dashboard' => [
        'clear_client_prefs' => true, // löscht lokale Browser-Einstellungen beim Laden
        // Top-Hero/Overlay-Bild im Dashboard-Header
        'header_image' => env('BRANDING_DASHBOARD_HEADER_IMAGE', null), // relativer Pfad ab Projekt-Root oder absolute URL
        'sidebar' => [
            // sidebar-white | sidebar-dark | sidebar-color | sidebar-transparent
            'color_class' => 'sidebar-white',
            // Weitere Typ-Klassen: sidebar-mini, sidebar-hover, sidebar-boxed
            'type_classes' => ['sidebar-base'],
            // navs-rounded | navs-rounded-all | navs-pill | navs-pill-all
            'item_class' => 'navs-rounded-all',
        ],
    ],
];
