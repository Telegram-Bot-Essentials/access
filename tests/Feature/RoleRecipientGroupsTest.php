<?php

declare(strict_types=1);

use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Access\Support\RoleRecipientGroups;
use TelegramBotEssentials\Essence\Contracts\RecipientGroups;

beforeEach(function () {
    $this->bot = $this->makeBot();
    $this->groups = app(RecipientGroups::class);

    $this->role = fn (string $name, $bot = null) => Role::withoutGlobalScopes()->create([
        'bot_id' => ($bot ?? $this->bot)->id,
        'name' => $name,
        'abilities' => [],
    ]);
});

it('offers roles as groups', function () {
    expect($this->groups)->toBeInstanceOf(RoleRecipientGroups::class);

    $role = ($this->role)('Sales');

    expect($this->groups->options($this->bot))->toHaveKey((string) $role->id, 'Sales');
});

it('returns everyone holding the roles, once each', function () {
    $sales = ($this->role)('Sales');
    $support = ($this->role)('Support');
    $both = $this->makeBotUser($this->bot, 7601);
    $one = $this->makeBotUser($this->bot, 7602);
    $none = $this->makeBotUser($this->bot, 7603);
    $sales->users()->attach([$both->id, $one->id]);
    $support->users()->attach($both->id);

    $ids = $this->groups->members($this->bot, [(string) $sales->id, (string) $support->id])->pluck('id')->sort()->values()->all();

    expect($ids)->toBe([$both->id, $one->id])->not->toContain($none->id);
});

it('leaves out holders the bot cannot message privately', function () {
    $role = ($this->role)('Sales');
    $groupOnly = $this->makeBotUser($this->bot, 7604, ['has_private_chat' => false]);
    $role->users()->attach($groupOnly->id);

    expect($this->groups->members($this->bot, [(string) $role->id]))->toBeEmpty();
});

it('ignores ids that are not roles of this bot', function () {
    $otherBot = $this->makeBot();
    $foreign = ($this->role)('Foreign', $otherBot);
    $foreign->users()->attach($this->makeBotUser($otherBot, 7605)->id);

    expect($this->groups->members($this->bot, [(string) $foreign->id, 'owner', '999999']))->toBeEmpty()
        ->and($this->groups->options($this->bot))->not->toHaveKey((string) $foreign->id);
});
