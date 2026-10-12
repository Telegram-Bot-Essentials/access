<?php

declare(strict_types=1);

use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Access\Permissions;
use TelegramBotEssentials\Access\Telegram\Features\Admin\RolesFeature;
use TelegramBotEssentials\Essence\Models\BotUser;

/*
 * Role management can be delegated, but a delegate can only hand out what
 * they hold. Every case posts a hand-made callback, as a forged one would
 * arrive: the buttons a screen renders are not the guard.
 */

beforeEach(function () {
    permissionRegistry()->register('reports.view', 'View reports', 'reports');
    permissionRegistry()->register('panels.manage', 'Manage panels', 'panels');

    $this->bot = $this->makeBot();

    // A delegate: may manage roles, and holds reports.view only.
    $this->delegate = $this->makeBotUser($this->bot, 801);
    $this->delegateRole = Role::query()->create([
        'bot_id' => $this->bot->id,
        'name' => 'Delegate',
        'permissions' => [Permissions::MANAGE_ROLES, 'reports.view'],
    ]);
    $this->delegateRole->users()->attach($this->delegate->id);

    $this->target = $this->makeBotUser($this->bot, 802);
    $this->empty = Role::query()->create(['bot_id' => $this->bot->id, 'name' => 'Empty', 'permissions' => []]);
    $this->panels = Role::query()->create(['bot_id' => $this->bot->id, 'name' => 'Panels', 'permissions' => ['panels.manage']]);

    $this->owner = BotUser::factory()->create([
        'bot_id' => $this->bot->id,
        'telegram_user_peer_id' => $this->bot->bot_owner_peer_id,
    ]);
});

function memberIds(Role $role): array
{
    return $role->users()->pluck('bot_users.id')->all();
}

it('lets a delegate grant a permission they hold', function () {
    $this->pressRoles(801, 'toggle', [$this->empty->id, RolesFeature::hash('reports.view'), 1]);

    expect($this->empty->fresh()->permissionList())->toBe(['reports.view']);
});

it('stops a delegate granting a permission they lack', function () {
    $this->pressRoles(801, 'toggle', [$this->empty->id, RolesFeature::hash('panels.manage'), 1]);

    expect($this->empty->fresh()->permissionList())->toBe([]);
});

it('stops a delegate granting the wildcard', function () {
    $this->pressRoles(801, 'toggle', [$this->empty->id, RolesFeature::hash(Permissions::WILDCARD), 1]);

    expect($this->empty->fresh()->permissionList())->toBe([]);
});

it('stops a delegate giving away a role holding more than they have', function () {
    $this->pressRoles(801, 'toggleUserRole', [$this->target->id, $this->panels->id]);

    expect(memberIds($this->panels))->toBe([]);
});

it('stops a delegate editing a role holding more than they have', function () {
    $this->pressRoles(801, 'toggle', [$this->panels->id, RolesFeature::hash('reports.view'), 1]);
    $this->pressRoles(801, 'confirmDelete', [$this->panels->id]);

    expect($this->panels->fresh()?->permissionList())->toBe(['panels.manage']);
});

it('lets a delegate give a role within their own permissions', function () {
    $this->pressRoles(801, 'toggleUserRole', [$this->target->id, $this->empty->id]);

    expect(memberIds($this->empty))->toBe([$this->target->id]);
});

it('stops anyone changing their own roles', function () {
    $this->pressRoles(801, 'toggleUserRole', [$this->delegate->id, $this->delegateRole->id]);

    expect(memberIds($this->delegateRole))->toBe([$this->delegate->id]);
});

it('stops anyone changing the owner\'s roles', function () {
    $this->pressRoles(801, 'toggleUserRole', [$this->owner->id, $this->empty->id]);

    expect(memberIds($this->empty))->toBe([]);
});

it('lets the owner do everything', function () {
    $ownerPeer = (int) $this->bot->bot_owner_peer_id;

    $this->pressRoles($ownerPeer, 'toggle', [$this->empty->id, RolesFeature::hash(Permissions::WILDCARD), 1]);
    $this->pressRoles($ownerPeer, 'toggleUserRole', [$this->target->id, $this->panels->id]);

    expect($this->empty->fresh()->permissionList())->toBe([Permissions::WILDCARD])
        ->and(memberIds($this->panels))->toBe([$this->target->id]);
});

it('keeps users without the manage permission out entirely', function () {
    $this->pressRoles(802, 'toggle', [$this->empty->id, RolesFeature::hash('reports.view'), 1]);

    expect($this->empty->fresh()->permissionList())->toBe([]);
});
