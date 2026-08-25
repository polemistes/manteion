<?php

/**
 * Entry point for hosts where the web root is a fixed folder (e.g. $HOME/www)
 * that cannot be pointed at this app's own public/ directory, so this app lives
 * one level up as a sibling instead (e.g. $HOME/manteion next to $HOME/www).
 *
 * This is a copy of public/index.php with its three "../" paths changed to
 * "../manteion/" to reach the sibling app folder. Deployed via deploy/sync-webroot.sh,
 * which copies this over the real public/index.php in the web root — never edit
 * public/index.php itself to point outside the app, since that file must stay
 * correct for local development and any host that supports a custom document root.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../manteion/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../manteion/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../manteion/bootstrap/app.php';

$app->handleRequest(Request::capture());
