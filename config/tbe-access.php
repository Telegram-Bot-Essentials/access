<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default roles
    |--------------------------------------------------------------------------
    |
    | The roles every bot starts with: role name => abilities ("*" for all).
    | They are created when a bot is created, and for existing bots by
    | `php artisan tbe:access:sync-roles`. A role that already exists is
    | never changed, so the owner's edits stay.
    |
    */

    'default_roles' => [
        'Admin' => ['*'],
    ],

];
