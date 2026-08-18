#!/usr/bin/env php
<?php

/**
 * Exit 0 when the catalogue is empty (so the caller should seed), 1 when it already has
 * products, 2 when the check itself could not run.
 *
 * Used by deploy/railway-start.sh. Deliberately NOT `artisan tinker --execute`: psysh
 * catches exit() and always returns 1, so an exit-code check through tinker reports
 * "populated" no matter what and a fresh database silently never gets seeded. Parsing
 * tinker's stdout is no better -- a PHP startup warning on the last line breaks it.
 */

require __DIR__ . '/../vendor/autoload.php';

try {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    exit(App\Models\Product::count() > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'db-needs-seed: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}
