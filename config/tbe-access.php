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
    | "main" is the bot whose unique_id is tbe-essence.main.unique_id;
    | "sub" is every other bot.
    |
    */

    'default_roles' => [
        'main' => [
            'Admin' => ['*'],
        ],
        'sub' => [
            'Admin' => ['*'],
        ],
    ],

];
