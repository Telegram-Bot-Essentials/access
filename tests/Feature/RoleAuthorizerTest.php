<?php

declare(strict_types=1);

use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Access\Permissions;
use TelegramBotEssentials\Access\Support\RoleAuthorizer;
use TelegramBotEssentials\Essence\Models\BotUser;

/*
 * Installing tbe-access swaps essence's owner-only default for roles: a
 * role's permissions decide.
 */

function giveRole(BotUser $user, array $permissions, string $name = 'Role'): Role
{
    $role = Role::query()->create([
        'bot_id' => $user->bot_id,
        'name' => $name,
        'permissions' => $permissions,
    ]);
    $role->users()->attach($user->id);
    accessManager()->forget($user);

    return $role;
}

beforeEach(function () {
    $this->bot = $this->makeBot();
    $this->user = $this->makeBotUser($this->bot, 701);
});

it('replaces essence\'s authorizer', function () {
    expect(authorizer())->toBeInstanceOf(RoleAuthorizer::class);
});

it('takes access away with the role', function () {
    $role = giveRole($this->user, ['reports.view']);
    $role->users()->detach($this->user->id);
    accessManager()->forget($this->user);

    expect(authorizer()->allows($this->user, 'reports.view'))->toBeFalse();
});

it('grants exactly the permissions a role holds', function () {
    giveRole($this->user, ['reports.view']);

    expect(authorizer()->allows($this->user, 'reports.view'))->toBeTrue()
        ->and(authorizer()->allows($this->user, 'panels.manage'))->toBeFalse();
});

it('unions the permissions of several roles', function () {
    giveRole($this->user, ['reports.view'], 'Reports');
    giveRole($this->user, ['panels.manage'], 'Panels');

    expect(authorizer()->allows($this->user, 'reports.view'))->toBeTrue()
        ->and(authorizer()->allows($this->user, 'panels.manage'))->toBeTrue();
});

it('lets the wildcard through everything', function () {
    giveRole($this->user, [Permissions::WILDCARD]);

    expect(authorizer()->allows($this->user, 'anything.at.all'))->toBeTrue();
});

it('finds the users a permission reaches, through a named grant or the wildcard', function () {
    $wildcard = $this->makeBotUser($this->bot, 702);
    $unrelated = $this->makeBotUser($this->bot, 703);
    giveRole($this->user, ['reports.view'], 'Reports');
    giveRole($wildcard, [Permissions::WILDCARD], 'Everything');
    giveRole($unrelated, ['panels.manage'], 'Panels');

    expect(authorizer()->usersWith($this->bot, 'reports.view')->orderBy('id')->pluck('id')->all())
        ->toBe([$this->user->id, $wildcard->id]);
});

it('keeps public handlers public and lets the owner through with no roles', function () {
    $owner = BotUser::factory()->create([
        'bot_id' => $this->bot->id,
        'telegram_user_peer_id' => $this->bot->bot_owner_peer_id,
    ]);

    expect(authorizer()->allows($this->user, null))->toBeTrue()
        ->and(authorizer()->allows($owner, 'reports.view'))->toBeTrue();
});

it('never reads another bot\'s roles', function () {
    $otherBot = $this->makeBot();
    $foreign = Role::query()->create(['bot_id' => $otherBot->id, 'name' => 'Foreign', 'permissions' => ['reports.view']]);
    $foreign->users()->attach($this->user->id);

    expect(authorizer()->allows($this->user, 'reports.view'))->toBeFalse();
});

it('registers its own permissions', function () {
    expect(permissionRegistry()->has(Permissions::MANAGE_ROLES))->toBeTrue();
});
