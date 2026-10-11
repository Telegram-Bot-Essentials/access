<?php

declare(strict_types=1);

use TelegramBotEssentials\Access\Telegram\ReplyKeys\Member\ExampleKey;

// A companion's ReplyKeys aren't auto-discovered - the consuming app lists
// them in config('tbe-essence.keyboard') and essence registers them from
// there. Do that one line here, standing in for the app's config.
beforeEach(fn () => replyKeyBus()->addReplyKey(ExampleKey::class));

it('replies when the example key is pressed', function () {
    $bot = $this->makeBot();

    $this->postWebhookUpdate($bot, $this->makeMessageUpdate('Example ✨'))
        ->assertOk();

    $this->assertTelegramSent(
        fn ($request) => str_contains((string) $request['text'], 'example key from telegram-bot-essentials/access')
    );
});
