<?php
/**
 * Konektor Standalone — Public Front Controller
 * Configure your web server to route all requests to this file.
 *
 * Dibuat Oleh  : Hanif Pramono
 * Website      : https://hanifprm.my.id
 *
 * Apache (.htaccess in public/):
 *   RewriteEngine On
 *   RewriteCond %{REQUEST_FILENAME} !-f
 *   RewriteRule ^ index.php [QSA,L]
 *
 * Nginx:
 *   try_files $uri $uri/ /index.php?$query_string;
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';
// Controllers loaded via autoloader in bootstrap.php

Router::registerPublicRoutes();
Router::dispatch();
