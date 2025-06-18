<?php

return [

    /*
    |---------------------------------------------------------------
    | Which extensions *may become* a “master” file?
    |---------------------------------------------------------------
    | Order does not matter; use lowercase without the leading dot.
    */
    'master_extensions' => [
        'par', 'asm',
        'doc', 'docx',
        'xls', 'xlsx',
    ],

    /*
    |---------------------------------------------------------------
    | Which extensions are considered “slaves” (documents
    | that must live in the *same folder* and share the same
    | part-name to make the master valid)?
    |---------------------------------------------------------------
    */
    'slave_extensions'  => [
        'pdf',
    ],
];
