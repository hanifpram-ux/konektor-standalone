<?php
/**
 * Konektor Standalone — Admin Bootstrap
 *
 * Dibuat Oleh  : Hanif Pramono
 * Website      : https://hanifprm.my.id
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$page = basename($_SERVER['SCRIPT_FILENAME']);
if ($page !== 'login.php' && !Auth::check()) {
    header('Location: ' . _adminBase() . '/login.php');
    exit;
}

function _adminBase() {
    static $base = null;
    if ($base !== null) return $base;

    $adminDir = str_replace('\\', '/', dirname(__DIR__));
    $docRoot  = rtrim(str_replace('\\', '/', isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : ''), '/');

    if ($docRoot && strpos($adminDir, $docRoot) === 0) {
        $base = '/' . ltrim(substr($adminDir, strlen($docRoot)), '/');
    } else {
        $scriptFile = str_replace('\\', '/', isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
        $scriptUrl  = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $depth = substr_count(
            ltrim(str_replace($adminDir, '', $scriptFile), '/'),
            '/'
        );
        $base = rtrim(dirname($scriptUrl, $depth + 1), '/');
    }
    return $base;
}

function adminPath($rel = '') {
    return _adminBase() . ($rel ? '/' . ltrim($rel, '/') : '');
}

function adminApiUrl() {
    // Build API URL from admin base: strip /admin suffix, add /api
    $base = rtrim(_adminBase(), '/');
    // _adminBase() returns e.g. /admin or /subdir/admin
    // strip trailing /admin segment to get site root
    $siteBase = preg_replace('#/admin$#i', '', $base);
    $scheme = knk_is_https() ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    return $scheme . '://' . $host . $siteBase . '/api';
}

function ae($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function flashSet($type, $msg) {
    $_SESSION['flash'] = compact('type', 'msg');
}

function flashGet() {
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
    unset($_SESSION['flash']);
    return $f;
}
