<?php

return [

  /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

  'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

  'allowed_methods' => ['*'],

  'allowed_origins' => [
    env('FRONTEND_URL', 'http://localhost:8080'),
    'http://localhost:5173',
    'http://localhost:3000',
    'https://memo-spark-two.vercel.app',
  ],

  'allowed_origins_patterns' => [
    '#^https://memo-spark.*\.vercel\.app$#',
  ],

  'allowed_headers' => ['*'],

  'exposed_headers' => [],

  'max_age' => 3600,

  'supports_credentials' => true,

];
