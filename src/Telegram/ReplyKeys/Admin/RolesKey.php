<?php

namespace TelegramBotEssentials\Access\Telegram\ReplyKeys\Admin;

use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Access\Permissions;
use TelegramBotEssentials\Access\Telegram\Features\Admin\RolesFeature;
use TelegramBotEssentials\Essence\Telegram\ReplyKeys\ReplyKey;

class RolesKey extends ReplyKey
{
    protected ?string $permission = Permissions::MANAGE_ROLES;

    protected function text(): string
    {
        return __('tbe-access::roles.reply_key');
    }

    /**
     * @throws TelegramSDKException
     */
    public function handle(): void
    {
        RolesFeature::index()->send();
    }
}
