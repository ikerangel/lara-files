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

    /* ───────────────────────────────────────────────────────────────
     |  Omit logic
     |    - Any folder whose name matches an entry in
     |      “omit_directories” must be omitted
     |    - Any folder whose name _starts with_ one of the prefixes
     |      in “omit_directory_prefixes” must be omitted
     ─────────────────────────────────────────────────────────────── */
    'omit_directories'        => [          // exact names
        'ARCHIVO', 'MODIFICAR', '.git', '.svn',
    ],

    'omit_directory_prefixes' => [          // anything that _starts with_
        '00',
        '_',
        '.',                       // 00*, _*, .* …
    ],

];
