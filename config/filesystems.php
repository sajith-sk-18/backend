<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        /*
         * Uploaded product images.
         *
         * Normally this is storage/app/public, exposed through the
         * public/storage symlink that `php artisan storage:link` creates.
         *
         * Hostinger shared hosting without SSH cannot create that symlink, and
         * the document root is public_html/ while the app lives beside it in
         * laravel-api/. Setting PUBLIC_DISK_SHARED_HOSTING=true makes uploads
         * write straight into public_html/api/storage instead, which Apache
         * already serves -- no symlink needed. base_path() is laravel-api/, so
         * the path stays relative and no absolute home directory is baked in.
         *
         * 'url' resolves to APP_URL/storage = https://flurotech.in/api/storage,
         * which is that same folder. Leave the flag unset for local development
         * and for any host where storage:link works.
         */
        'public' => [
            'driver' => 'local',
            'root' => env('PUBLIC_DISK_SHARED_HOSTING', false)
                ? base_path('../public_html/api/storage')
                : storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
