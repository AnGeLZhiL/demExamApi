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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'gogs' => [
        'url' => env('GOGS_URL', 'http://213441fe8ea4.vps.myjino.ru:3000'),
        'api_token' => env('GOGS_API_TOKEN', '9252159489cf6b1f3cb6e1575010d2db103b10b8'),
        'admin_username' => env('GOGS_ADMIN_USERNAME', 'adminangelina'),
        'admin_email' => env('GOGS_ADMIN_EMAIL', 'angelina.zhilyakova.2002@mail.ru'),
        'org_prefix' => env('GOGS_ORG_PREFIX', 'exam'),
        'mock' => env('GOGS_MOCK', false),
        'repo' => [
            'visibility' => 'private',
            'auto_init' => true,
            'default_branch' => 'main',
        ],
        'user' => [
            'password_length' => 10,
            'email_domain' => '@exam.demo.local',
            'send_notify' => false,
        ],
        'timeout' => 30,
        'retry_attempts' => 2,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ],

];
