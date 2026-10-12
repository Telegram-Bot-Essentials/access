<?php

declare(strict_types=1);

namespace TelegramBotEssentials\Access\Tests;

use TelegramBotEssentials\Access\TbeAccessServiceProvider;
use TelegramBotEssentials\Access\Telegram\Features\Admin\RolesFeature;
use TelegramBotEssentials\Essence\Testing\TestCase as EssenceTestCase;
use TelegramBotEssentials\UserManagement\TbeUserManagementServiceProvider;

abstract class TestCase extends EssenceTestCase
{
    protected function getPackageProviders($app): array
    {
        // tbe-access first, as package discovery orders it in a real app
        // ("access" < "essence"): its binding must survive essence's.
        return [
            TbeAccessServiceProvider::class,
            ...parent::getPackageProviders($app),
            TbeUserManagementServiceProvider::class,
        ];
    }

    /**
     * Press a roles-screen button as $peerId, with hand-made callback data
     * - exactly what a forged press would send.
     *
     * @param  array<int, int|string>  $params
     */
    protected function pressRoles(int $peerId, string $method, array $params = []): void
    {
        $this->postWebhookUpdate(
            $this->bot,
            $this->makeCallbackQueryUpdate(encodeCallback(RolesFeature::TYPE, $method, $params), peerId: $peerId),
        )->assertOk();
    }
}
