<?php

use TelegramBotEssentials\Access\Services\AccessManager;

if (! function_exists('accessManager')) {
    function accessManager(): AccessManager
    {
        return app(AccessManager::class);
    }
}
