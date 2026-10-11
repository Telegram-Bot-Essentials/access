<?php

declare(strict_types=1);

namespace TelegramBotEssentials\Access\Tests;

use TelegramBotEssentials\Essence\Testing\TestCase as EssenceTestCase;
use TelegramBotEssentials\Access\TbeAccessServiceProvider;

abstract class TestCase extends EssenceTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            TbeAccessServiceProvider::class,
        ]);
    }
}
