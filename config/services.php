<?php

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
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Renderer (React Frontend)
    |--------------------------------------------------------------------------
    |
    | The URL of the React frontend dev server that provides the email
    | rendering endpoint. This allows Laravel to render React email templates.
    |
    */
    'email_renderer' => [
        'url' => env('EMAIL_RENDERER_URL', 'http://localhost:5173'),
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

    /*
    |--------------------------------------------------------------------------
    | Jitsi Meet Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Jitsi video conferencing integration.
    |
    */
    'jitsi' => [
        'domain' => env('JITSI_DOMAIN', 'meet.codagenz.com'),
        'app_id' => env('JITSI_APP_ID', 'lms-production'),
        'app_secret' => env('JITSI_APP_SECRET', ''),
        'jibri_url' => env('JIBRI_URL', 'http://localhost:2222'),
    ],

   
    /*
    |--------------------------------------------------------------------------
    | Gemini Configuration
    |--------------------------------------------------------------------------
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'summary_model' => env('GEMINI_SUMMARY_MODEL', 'gemini-2.5-flash'),
    ],

    /*
    |--------------------------------------------------------------------------
    | YouTube Data API v3 (for AI Tutor resource discovery)
    |--------------------------------------------------------------------------
    */
    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY', ''),
    ],

];
