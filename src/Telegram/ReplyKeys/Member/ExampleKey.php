<?php

namespace TelegramBotEssentials\Access\Telegram\ReplyKeys\Member;

use TelegramBotEssentials\Essence\Telegram\ReplyKeys\ReplyKey;

/**
 * A working reference implementation, not a placeholder - rename or delete
 * it once you've built your own. See tests/Feature/ExampleKeyTest.php for
 * how to exercise it, and the README for why a companion's ReplyKeys need
 * an explicit line to register in tests (they aren't auto-discovered).
 */
class ExampleKey extends ReplyKey
{
    protected int $perm = 0;

    protected function text(): string
    {
        return __('tbe-access::example.reply_key');
    }

    protected function response(): string
    {
        return __('tbe-access::example.response');
    }

    public function handle(): void
    {
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => $this->getResponse(),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);
    }
}
