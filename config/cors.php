<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Vite defaults for this repo's two apps (customer 5173 / admin 5174).
        // Added 17-Aug: only 5175/5176 were listed, so both apps hit a CORS
        // error on their real ports.
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5174',
        'http://localhost:5175',
        'http://127.0.0.1:5175',
        'http://localhost:5176',
        'http://127.0.0.1:5176',
    ],

    'allowed_origins_patterns' => [
        // Vercel gives every production, branch and preview deployment its own
        // subdomain, so listing exact origins would break on each new preview.
        // Anchored at both ends: an unanchored pattern would also match a
        // hostile origin such as https://vercel.app.attacker.com.
        '#^https://[a-z0-9-]+\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
