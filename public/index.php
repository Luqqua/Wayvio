<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

// Mirror the health/extension checks from the project root while keeping
// the web entry point inside /public for proper server hardening.
$installing_file_exists = file_exists(__DIR__ . '/../INSTALLING');
$installer_lock_exists = file_exists(__DIR__ . '/../INSTALLERLOCK');
$is_installed = file_exists(__DIR__ . '/../storage/app/ISINSTALLED');

if (($installing_file_exists || $installer_lock_exists) && $is_installed) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Installer markers detected on an installed instance. Remove INSTALLING/INSTALLERLOCK before startup.';
    exit(1);
}

if ($installing_file_exists) {
    $required_extensions = array('bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'json', 'mbstring', 'openssl', 'pcre', 'pdo', 'tokenizer', 'xml', 'iconv');

    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            throw new Exception('PHP extension ' . $ext . ' is not installed on your system');
        }
    }
}

define('LARAVEL_START', microtime(true));

// ------------------------------------------------------------------------
// Maintenance mode
// ------------------------------------------------------------------------
if (file_exists(__DIR__ . '/../storage/framework/maintenance.php')) {
    require __DIR__ . '/../storage/framework/maintenance.php';
}

// ------------------------------------------------------------------------
// Register the auto loader
// ------------------------------------------------------------------------
require __DIR__ . '/../vendor/autoload.php';

// ------------------------------------------------------------------------
// Run the application
// ------------------------------------------------------------------------
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = tap($kernel->handle(
    $request = Request::capture()
))->send();

$kernel->terminate($request, $response);
