<?php

declare(strict_types=1);

use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Access\Telegram\Features\Admin\RolesFeature;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Models\TelegramUser;

/*
 * The owner's path through the screens: create a role, add a member, see
 * the Roles button on a user's profile.
 */

beforeEach(function () {
    $this->bot = $this->makeBot();
    $this->ownerPeer = (int) $this->bot->bot_owner_peer_id;
    $this->owner = BotUser::factory()->create([
        'bot_id' => $this->bot->id,
        'telegram_user_peer_id' => $this->ownerPeer,
    ]);
});

it('creates a role from a typed name', function () {
    $this->postWebhookUpdate($this->bot, $this->makeCallbackQueryUpdate(encodeCallback(RolesFeature::TYPE, 'create'), peerId: $this->ownerPeer))->assertOk();
    $this->postWebhookUpdate($this->bot, $this->makeMessageUpdate('Support', peerId: $this->ownerPeer))->assertOk();

    expect(Role::query()->where('bot_id', $this->bot->id)->pluck('name')->all())->toBe(['Support'])
        ->and($this->owner->privateConversation()->state)->toBeNull();
});

it('refuses a duplicate name and keeps waiting for another', function () {
    Role::query()->create(['bot_id' => $this->bot->id, 'name' => 'Support', 'permissions' => []]);

    $this->postWebhookUpdate($this->bot, $this->makeCallbackQueryUpdate(encodeCallback(RolesFeature::TYPE, 'create'), peerId: $this->ownerPeer))->assertOk();
    $this->postWebhookUpdate($this->bot, $this->makeMessageUpdate('Support', peerId: $this->ownerPeer))->assertOk();

    expect(Role::query()->count())->toBe(1)
        ->and($this->owner->privateConversation()->state)->not->toBeNull();
});

it('adds a member by @username', function () {
    $role = Role::query()->create(['bot_id' => $this->bot->id, 'name' => 'Support', 'permissions' => []]);
    $member = $this->makeBotUser($this->bot, 901);
    TelegramUser::query()->where('peer_id', 901)->update(['username' => 'helper']);

    $this->postWebhookUpdate($this->bot, $this->makeCallbackQueryUpdate(encodeCallback(RolesFeature::TYPE, 'addMember', [$role->id]), peerId: $this->ownerPeer))->assertOk();
    $this->postWebhookUpdate($this->bot, $this->makeMessageUpdate('@helper', peerId: $this->ownerPeer))->assertOk();

    expect($role->users()->pluck('bot_users.id')->all())->toBe([$member->id]);
});

it('shows the Roles button on another user\'s profile, not on your own', function () {
    $member = $this->makeBotUser($this->bot, 902);
    $this->postWebhookUpdate($this->bot, $this->makeMessageUpdate('hi', peerId: $this->ownerPeer))->assertOk();

    $keys = fn (BotUser $user) => userManagementSections()->getSectionsFor($user)->pluck('key')->all();

    expect($keys($member))->toContain('access_roles')
        ->and($keys($this->owner))->not->toContain('access_roles');
});

it('lists the registered permissions on the checklist, wildcard first', function () {
    permissionRegistry()->register('reports.view', 'View reports', 'reports');

    expect(RolesFeature::catalog())->toContain('*', 'access.roles.manage', 'reports.view')
        ->and(RolesFeature::catalog()[0])->toBe('*')
        ->and(RolesFeature::fromHash(RolesFeature::hash('reports.view')))->toBe('reports.view');
});
