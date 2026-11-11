<?php
declare(strict_types=1);

function app_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = APP_ROOT . '/config.php';
        $cfg = is_file($file) ? (require $file) : [];
        $env = ['db_host' => 'DB_HOST', 'db_name' => 'DB_NAME', 'db_user' => 'DB_USER', 'db_password' => 'DB_PASSWORD'];
        foreach ($env as $key => $var) {
            $val = getenv($var);
            if ($val !== false && $val !== '') {
                $cfg[$key] = $val;
            }
        }
    }
    return $cfg;
}

function fail_500(string $logMessage): never
{
    error_log('[toastmasters-timer] ' . $logMessage);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    exit('Something went wrong. Please try again later.');
}

function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        $cfg = app_config();
        foreach (['db_host', 'db_name', 'db_user', 'db_password'] as $key) {
            if (!isset($cfg[$key]) || $cfg[$key] === '') {
                fail_500("Missing database setting: $key (see config.sample.php)");
            }
        }
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $conn = new mysqli($cfg['db_host'], $cfg['db_user'], $cfg['db_password'], $cfg['db_name']);
            $conn->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            fail_500('Database connection failed: ' . $e->getMessage());
        }
    }
    return $conn;
}
