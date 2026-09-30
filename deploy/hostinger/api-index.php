<?php

/**
 * Front controller for https://flurotech.in/api  (Hostinger, single origin).
 *
 * Upload this file as  public_html/api/index.php.
 *
 * The Laravel app itself lives OUTSIDE the document root, at
 * ~/domains/flurotech.in/laravel-api/, so .env, vendor/ and storage/ can never be
 * fetched over HTTP. Only this file and .htaccess sit inside public_html.
 * (Serving the whole project folder is how .env and stray scripts get exposed --
 * do not "simplify" this by moving the app under public_html.)
 *
 * Paths below are relative to public_html/api/, so they resolve to
 * ../../laravel-api/ regardless of the account's absolute home path.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$base = __DIR__ . '/../../laravel-api';

if (file_exists($maintenance = $base . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base . '/vendor/autoload.php';

/** @var Application $app */
$app = require_once $base . '/bootstrap/app.php';

$app->handleRequest(Request::capture());
