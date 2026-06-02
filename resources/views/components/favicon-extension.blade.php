<?php

if (!function_exists('localIcon')) {
    function localIcon($id)
    {
        $normalizedId = (int) $id;
        if ($normalizedId <= 0) {
            return 'error.error';
        }

        $directory = base_path("assets/favicon/icons");
        if (!is_dir($directory)) {
            return 'error.error';
        }

        $files = scandir($directory);
        if ($files === false) {
            return 'error.error';
        }

        $pathinfo = "error.error";
        $pattern = '/^' . preg_quote((string) $normalizedId, '/') . '\.([a-z0-9]+)$/i';

        foreach ($files as $file) {
            if (!is_string($file)) {
                continue;
            }

            if (preg_match($pattern, $file, $matches) === 1) {
                $pathinfo = $normalizedId . "." . strtolower((string) ($matches[1] ?? pathinfo($file, PATHINFO_EXTENSION)));
                break;
            }
        }

        return $pathinfo;
    }
}
  
?>
