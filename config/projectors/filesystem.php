<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Extensions that must be ignored
    |--------------------------------------------------------------------------
    | Write them without the leading dot.  Comparison is case-insensitive.
    */
    'omit_extensions' => [
        'cfg',
        'db',
        // …
    ],

    /*
    |--------------------------------------------------------------------------
    | Directory names that must be ignored completely
    |--------------------------------------------------------------------------
    | Example: build, _trash, debug …  Case-insensitive.
    */
    'omit_directories' => [
        'build',
        'debug',
        // …
    ],

    /*
    |--------------------------------------------------------------------------
    | Directory name prefixes to omit
    |--------------------------------------------------------------------------
    | Each entry is compared with “startsWith”.  The most common request is
    | to drop *every* directory that starts with “00”.
    */
    'omit_directory_prefixes' => [
        '00',      // ignore 00, 001_tmp, 00-old, 00Whatever …
        // add more prefixes here …
    ],
];
