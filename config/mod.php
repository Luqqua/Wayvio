<?php

$envAllowed = array_filter(array_map('trim', explode(',', env('MOD_ALLOWED_COMMANDS', ''))));

return [
    // Liste erlaubter Mod-Commands (exakte Artisan-Namen), z. B. ['user:list', 'link:toggle'].
    // Kann per ENV `MOD_ALLOWED_COMMANDS="user:list,link:toggle"` überschrieben werden.
    // '*' erlaubt alle registrierten Mod-Commands.
    // Default: alle erlaubt (['*']) falls ENV leer bleibt.
    'allowed_commands' => $envAllowed ?: ['*'],

    // E-Mail-Ausgabe in user:list (unabhängig von Site-Rollen) – default: ausgeblendet.
    'user_list_show_email' => false,
    // E-Mail-Ausgabe in user:view
    'user_view_show_email' => true,
];
