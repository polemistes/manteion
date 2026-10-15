<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * Firebase Cloud Messaging, for pushes to the Android app: the absolute
     * path of the Firebase project's service-account key (JSON), and the
     * project id when it should differ from the key file's own.
     */
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS'),
        'project_id' => env('FCM_PROJECT_ID'),
    ],

    /*
     * The Android app: the stable download address of the newest signed
     * build (attached to every GitHub release by the mobile-android
     * workflow), and where the app reads the newest version from.
     */
    'android' => [
        'download_url' => env('ANDROID_APP_DOWNLOAD_URL', 'https://github.com/polemistes/manteion/releases/latest/download/manteion.apk'),
        'latest_release_api' => env('ANDROID_APP_LATEST_RELEASE_API', 'https://api.github.com/repos/polemistes/manteion/releases/latest'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
