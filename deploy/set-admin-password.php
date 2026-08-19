#!/usr/bin/env php
<?php

/**
 * Set an admin user's password from environment variables, at container boot.
 *
 * Why this exists: this stack has NO other way to administer credentials. The API has no
 * password-reset route (customer registration and reset were deliberately removed), the
 * admin panel has no change-password screen, Railway's MySQL has no public endpoint, and
 * `railway ssh` needs an SSH key registered to the account. Without this, the seeded
 * password `admin@example.com` / `password` -- which is public in this repo -- could not
 * be changed at all on a deployed instance.
 *
 * Driven by deploy/railway-start.sh, which only calls it when ADMIN_PASSWORD is set.
 *
 *   ADMIN_PASSWORD   required; the new password. Unset it once applied.
 *   ADMIN_EMAIL      optional; defaults to admin@example.com
 *
 * Exit codes:  0 changed   1 nothing to do   2 failed
 */

require __DIR__ . '/../vendor/autoload.php';

$password = getenv('ADMIN_PASSWORD') ?: '';
$email    = getenv('ADMIN_EMAIL') ?: 'admin@example.com';

if ($password === '') {
    fwrite(STDERR, "set-admin-password: ADMIN_PASSWORD is empty, nothing to do\n");
    exit(1);
}

try {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $user = App\Models\User::where('email', $email)->first();
    if (!$user) {
        fwrite(STDERR, "set-admin-password: no user with email {$email}\n");
        exit(2);
    }

    // Already correct? Then say so and change nothing, so a redeploy is a no-op and the
    // log does not imply a change that did not happen.
    if (Illuminate\Support\Facades\Hash::check($password, $user->password)) {
        echo "set-admin-password: {$email} already has this password\n";
        exit(1);
    }

    $user->password = bcrypt($password);
    $user->save();

    // Never echo the password itself -- container logs are retained.
    echo "set-admin-password: password updated for {$email}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'set-admin-password: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}
