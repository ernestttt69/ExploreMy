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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'google_maps' => [
        'routes_api_key' => env('GOOGLE_MAPS_ROUTES_API_KEY'),
        'browser_api_key' => env('GOOGLE_MAPS_BROWSER_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'weather' => [
        'api_key' => env('WEATHER_API_KEY'),
        'endpoint' => env('WEATHER_API_ENDPOINT', 'https://api.open-meteo.com/v1/forecast'),
        'historical_endpoint' => env('WEATHER_HISTORICAL_ENDPOINT', 'https://archive-api.open-meteo.com/v1/archive'),
        'timeout' => (int) env('WEATHER_TIMEOUT', 3),
    ],

    'places' => [
        'geocoding_endpoint' => env('PLACES_GEOCODING_ENDPOINT', 'https://geocoding-api.open-meteo.com/v1/search'),
        'timeout' => (int) env('PLACES_TIMEOUT', 3),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'ollama'),
        'endpoint' => env('AI_API_ENDPOINT'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL'),
        'timeout' => (int) env('AI_TIMEOUT', 45),
    ],

    'ollama' => [
        'endpoint' => env('OLLAMA_ENDPOINT', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen3:4b-instruct'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 90),
    ],

];
