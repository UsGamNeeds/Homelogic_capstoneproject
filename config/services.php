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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
        // Hard off by default. A key alone must never be enough to enable
        // transmission of resident data to the vendor - see
        // AI_ASSISTANT_ENABLED in .env.example.
        'enabled' => env('AI_ASSISTANT_ENABLED', false),
    ],

    // Push notifications to the native mobile app, sent through Expo.
    'expo_push' => [
        // Off switch for phone alerts. Turning it off stops sending at once;
        // in-app notifications and registered devices are left untouched.
        'enabled' => env('EXPO_PUSH_ENABLED', true),
        // Off by default. A push passes through Expo's and Apple's servers and
        // can be read on a lock screen or a watch, and notification messages
        // name residents, so the phone gets the title only. The full message
        // stays in the app, behind sign-in.
        'show_details' => env('EXPO_PUSH_SHOW_DETAILS', false),
    ],

];
