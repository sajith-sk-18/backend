<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // This app is SPA + JSON API: there is no Laravel-rendered
        // password.reset route. Override the URL builder so the reset
        // notification embeds a link to the customer-site form.
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $front = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
            return $front . '/reset-password?token=' . $token
                . '&email=' . urlencode($user->getEmailForPasswordReset());
        });
    }
}
