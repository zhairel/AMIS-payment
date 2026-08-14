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

    'google_vision' => [
        'key' => env('GOOGLE_VISION_KEY'),
    ],

    'paddle_ocr' => [
        'enabled' => env('PADDLE_OCR_ENABLED', true),
        'python' => env('OCR_PYTHON_BINARY', env('PADDLE_OCR_PYTHON', base_path('.venv-ocr/bin/python'))),
        'timeout' => env('PADDLE_OCR_TIMEOUT', 75),
    ],

    'paperless' => [
        'enabled' => env('PAPERLESS_ENABLED', true),
        'url' => rtrim(env('PAPERLESS_URL', 'http://127.0.0.1:8000'), '/'),
        'token' => env('PAPERLESS_API_TOKEN', ''),
        'timeout' => (int) env('PAPERLESS_TIMEOUT', 90),
    ],

    'tesseract' => [
        'timeout' => env('TESSERACT_OCR_TIMEOUT', 60),
    ],

    'receipt_ocr' => [
        'python' => env('OCR_PYTHON_BINARY', base_path('.venv-ocr/bin/python')),
        'url' => env('OCR_SERVICE_URL', null),
        'token' => env('OCR_SERVICE_TOKEN', null),
        'confidence_threshold' => (float) env('RECEIPT_OCR_CONFIDENCE_THRESHOLD', 0.72),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
        'tenant' => env('MICROSOFT_TENANT_ID', 'common'),
    ],

];
