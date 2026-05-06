<?php
/**
 * Konektor Standalone — Bootstrap
 * PHP 7.1+ compatible.
 *
 * Dibuat Oleh  : Hanif Pramono
 * Website      : https://hanifprm.my.id
 */

define('KONEKTOR_ROOT', dirname(__DIR__));
define('KONEKTOR_VERSION', '1.0.0');

$config_file = KONEKTOR_ROOT . '/config/config.php';
$lock_file   = KONEKTOR_ROOT . '/config/installed.lock';

if (!file_exists($lock_file) || !file_exists($config_file)) {
    $self = basename(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
    if ($self !== 'install.php') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $docRoot      = rtrim(str_replace('\\', '/', isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : ''), '/');
        $standaloneDir = str_replace('\\', '/', KONEKTOR_ROOT);
        if ($docRoot && strpos($standaloneDir, $docRoot) === 0) {
            $installPath = '/' . ltrim(substr($standaloneDir, strlen($docRoot)), '/') . '/install.php';
        } else {
            $installPath = '/install.php';
        }
        header('Location: ' . $scheme . '://' . $host . $installPath);
        exit;
    }
    return;
}

require_once $config_file;

// Autoloader
spl_autoload_register(function ($class) {
    $map = [
        'DB'          => 'core/DB.php',
        'Auth'        => 'core/Auth.php',
        'Router'      => 'core/Router.php',
        'Crypto'      => 'core/Crypto.php',
        'Helper'      => 'core/Helper.php',
        'Logger'      => 'core/Logger.php',
        'Campaign'    => 'models/Campaign.php',
        'Operator'    => 'models/Operator.php',
        'Lead'        => 'models/Lead.php',
        'Rotator'     => 'models/Rotator.php',
        'Blocker'     => 'models/Blocker.php',
        'Analytics'   => 'models/Analytics.php',
        'Settings'    => 'models/Settings.php',
        'PublicController' => 'api/PublicController.php',
        'CsController'     => 'api/CsController.php',
        'ApiController'    => 'api/ApiController.php',
        'MetaApi'     => 'integrations/MetaApi.php',
        'GoogleApi'   => 'integrations/GoogleApi.php',
        'TiktokApi'   => 'integrations/TiktokApi.php',
        'SnackApi'    => 'integrations/SnackApi.php',
        'TelegramApi' => 'integrations/TelegramApi.php',
    ];
    $base = KONEKTOR_ROOT . '/app/';
    if (isset($map[$class])) {
        require_once $base . $map[$class];
    }
});

// DB init
DB::init(
    KONEKTOR_DB_HOST,
    KONEKTOR_DB_PORT,
    KONEKTOR_DB_NAME,
    KONEKTOR_DB_USER,
    KONEKTOR_DB_PASS,
    KONEKTOR_DB_PREFIX
);

// Session — PHP 7.1 compatible (array form for session_set_cookie_params not available until 7.3)
if (session_status() === PHP_SESSION_NONE) {
    session_name('knk_session');
    $secure   = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(0, '/', '', $secure, true);
    session_start();
}
