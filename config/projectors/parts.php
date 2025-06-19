<?php

return [

    /* extensions regarded as “parts” */
    'part_extensions' => [
        'par',
        'asm',
        'doc',
        'docx',
        'xls',
        'xlsx',
    ],

    /* reuse the same omit rules so that ARCHIVO, EN REVISION, 00* … are skipped */
    'omit_directories'        => config('projectors.masterfiles.omit_directories'),
    'omit_directory_prefixes' => config('projectors.masterfiles.omit_directory_prefixes'),
];
