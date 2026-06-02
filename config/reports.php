<?php

return [
    // Anzahl Reports pro Minute pro IP (Route-Limit).
    'rate_limit_per_minute' => (int) env('REPORT_RATE_LIMIT_PER_MINUTE', 5),

    // Anzahl Reports pro Tag pro IP über alle Zielprofile hinweg.
    'rate_limit_per_day' => (int) env('REPORT_RATE_LIMIT_PER_DAY', 60),

    // Anzahl Reports pro Stunde pro IP und gemeldeter Seite.
    'rate_limit_target_per_hour' => (int) env('REPORT_TARGET_RATE_LIMIT_PER_HOUR', 3),

    // Falls false, kann die eigene Seite nicht gemeldet werden.
    'allow_self_report' => (bool) env('REPORT_ALLOW_SELF_REPORT', false),

    // Wenn true, muss der Report-Captcha-Kontext vollständig konfiguriert sein.
    'captcha_required' => (bool) env('REPORT_CAPTCHA_REQUIRED', false),

    // Verarbeitete Reports werden nach X Tagen endgültig gelöscht (0 = deaktiviert).
    'retention_days' => (int) env('REPORT_RETENTION_DAYS', 180),
];
