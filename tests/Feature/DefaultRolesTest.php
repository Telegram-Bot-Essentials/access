<?php

declare(strict_types=1);

use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Essence\Models\Bot;

/*
 * Every bot starts with the configured default roles: on creation, and for
 * bots that already exist through tbe:access:sync-roles. A role that
 * exists is never changed.
 */

function roleNames(Bot $bot): array
{
    return Role::withoutGlobalScopes()->where('bot_id', $bot->id)->orderBy('name')->pluck('abilities', 'name')->all();
}

beforeEach(function () {
    config([
        'tbe-essence.main.unique_id' => 'main',
        'tbe-access.default_roles' => [
            'main' => ['Admin' => ['*'], 'Support' => ['user-management.users.manage']],
            'sub' => ['Admin' => ['*']],
        ],
    ]);
});

it('seeds the main bot\'s default roles when it is created', function () {
    $bot = Bot::factory()->create(['unique_id' => 'main']);

    expect(roleNames($bot))->toBe(['Admin' => ['*'], 'Support' => ['user-management.users.manage']]);
});

it('seeds the sub-bot defaults on any other bot', function () {
    $bot = Bot::factory()->create();

    expect(roleNames($bot))->toBe(['Admin' => ['*']]);
});

it('creates missing roles on existing bots and leaves edited ones alone', function () {
    config(['tbe-access.default_roles.sub' => []]);
    $bot = Bot::factory()->create();
    Role::withoutGlobalScopes()->create(['bot_id' => $bot->id, 'name' => 'Admin', 'abilities' => ['reports.view']]);
    config(['tbe-access.default_roles.sub' => ['Admin' => ['*'], 'Billing' => ['billing.invoices.manage']]]);

    $this->artisan('tbe:access:sync-roles')->assertSuccessful();

    expect(roleNames($bot))->toBe(['Admin' => ['reports.view'], 'Billing' => ['billing.invoices.manage']]);
});

it('syncs only the bots it is given', function () {
    config(['tbe-access.default_roles.sub' => []]);
    $one = Bot::factory()->create();
    $two = Bot::factory()->create();
    config(['tbe-access.default_roles.sub' => ['Admin' => ['*']]]);

    $this->artisan('tbe:access:sync-roles', ['--bot' => [(string) $one->id]])->assertSuccessful();

    expect(roleNames($one))->toBe(['Admin' => ['*']])
        ->and(roleNames($two))->toBe([]);
});

it('is safe to run again', function () {
    $bot = Bot::factory()->create();

    $this->artisan('tbe:access:sync-roles')->assertSuccessful();
    $this->artisan('tbe:access:sync-roles')->assertSuccessful();

    expect(Role::withoutGlobalScopes()->where('bot_id', $bot->id)->count())->toBe(1);
});
