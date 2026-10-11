<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use TelegramBotEssentials\Access\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
