<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
     */

    'mailgun'   => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses'       => [
        'key'    => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe'    => [
        'model'  => App\User::class,
        'key'    => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'slack'     => [
        'client_id' => env('SLACK_APP_TRYBOT_CLIENT_ID'),
        'client_secret' => env('SLACK_APP_TRYBOT_CLIENT_SECRET'),
        'redirect' => env('SLACK_APP_TRYBOT_REDIRECT_URI'),
        'legacy_token' => env('SLACK_LEGACY_TOKEN'),
        'users'        => [
            'trybot'     => env('SLACK_APP_TRYBOT_OAUTH_ACCESS_TOKEN'),
            'fantasybot' => env('SLACK_APP_FANTASYBOT_OAUTH_ACCESS_TOKEN'),
        ],
        'verification_token' => env('SLACK_APP_TRYBOT_VERIFICATION_TOKEN'),
        'modqueue_webhook' => env('SLACK_MEOWBOT_REDDIT_MODQUEUE_WEBHOOK'),
    ],
    'google'    => [
        'knowledge_graph' => env('GOOGLE_KNOWLEDGE_GRAPH_TOKEN'),
        'time_zone_api'   => env('GOOGLE_TIME_ZONE_API_KEY'),
        'geocoding'       => env('GOOGLE_GEOCODING_API_KEY'),
    ],
    'api_ai'    => [
        'trybot' => env('API_AI_CLIENT_ACCESS_TOKEN'),
    ],
    'discord' => [
        'trybot_token' => env('DISCORD_TRYBOT_TOKEN'),
    ],
    'reddit' => [
        'rss_feed' => [
            'token' => env('REDDIT_RSS_FEED_TOKEN'),
            'user' => env('REDDIT_RSS_FEED_USER'),
        ],
    ],
];
