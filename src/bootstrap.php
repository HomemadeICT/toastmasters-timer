<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// Shared hosts often ship php.ini with display_errors on and don't allow php_flag in .htaccess.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/site.php';
require_once __DIR__ . '/limits.php';
require_once __DIR__ . '/export.php';
require_once __DIR__ . '/maintenance.php';

if (PHP_SAPI !== 'cli') {
    set_exception_handler(function (Throwable $e): void {
        fail_500(get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    });
    send_security_headers();
    start_secure_session();
}
