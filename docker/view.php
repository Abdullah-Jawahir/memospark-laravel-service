<?php

return [

  /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    */

  'paths' => [
    resource_path('views'),
  ],

  /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    | NOTE: Do NOT wrap in realpath() — it returns false if the directory
    | does not exist at boot time (e.g. inside a fresh Docker container),
    | which causes Laravel to throw "Please provide a valid cache path".
    */

  'compiled' => env(
    'VIEW_COMPILED_PATH',
    storage_path('framework/views')
  ),

];
