<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
        // Basic Auth sur l'URL du webhook (recommandation officielle
        // Postmark) — jamais la session organisateur, un webhook n'en
        // porte pas.
        'webhook_username' => env('POSTMARK_WEBHOOK_USERNAME'),
        'webhook_password' => env('POSTMARK_WEBHOOK_PASSWORD'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        // Numéro WhatsApp Business approuvé chez Twilio, sans le préfixe
        // "whatsapp:" (ajouté au moment de l'appel) — ex. +14155238886.
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        // Distinct de la clé secrète : signe les événements de webhook,
        // jamais utilisé pour appeler l'API Stripe (T-052).
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'flutterwave' => [
        'secret' => env('FLUTTERWAVE_SECRET'),
        // Hash secret dédié aux webhooks (flutterwave-signature), distinct
        // de la clé secrète d'API — même principe que Stripe (T-053).
        'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
        // Environnement sandbox par défaut, en attendant un compte réel —
        // à remplacer par l'URL de production le moment venu.
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://developersandbox-api.flutterwave.com'),
    ],

];
