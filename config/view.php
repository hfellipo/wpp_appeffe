<?php

// Important:
// - Some hosts end up with VIEW_COMPILED_PATH="" in .env, which would crash Blade.
// - On fresh servers storage/framework/views may not exist yet, so realpath() returns false.
$compiledFromEnv = trim((string) env('VIEW_COMPILED_PATH', ''));
$defaultCompiledPath = realpath(storage_path('framework/views')) ?: storage_path('framework/views');

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your views. Of course
    | the usual Laravel view path has already been registered for you.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    */

    'compiled' => $compiledFromEnv !== '' ? $compiledFromEnv : $defaultCompiledPath,

];

