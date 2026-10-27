<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin panel allow-list
    |--------------------------------------------------------------------------
    |
    | Email addresses (comma-separated in ADMIN_EMAILS) that may enter the
    | admin panel. An empty list lets nobody in.
    |
    */

    'emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

];
