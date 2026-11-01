<?php

namespace TelegramBotEssentials\Access\Support;

use TelegramBotEssentials\Access\Services\AccessManager;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * The Gate::before rule that lets roles decide. Essence asks the Gate with
 * the BotUser:
 *
 *  - the bot owner and the developer may use anything, checked before any
 *    role table is read;
 *  - a role that grants the ability (or "*") lets the user through;
 *  - anything else gets null, so other Gate rules still get a say and,
 *    with none, the ability is denied.
 *
 * Only BotUsers are answered: web users (Telescope, Log Viewer, ...) are
 * left to the app's own gates.
 */
class RoleGate
{
    public function __construct(private readonly AccessManager $access) {}

    public function before(mixed $user, string $ability): ?bool
    {
        if (! $user instanceof BotUser) {
            return null;
        }

        if ($this->access->isSuperuser($user) || $this->access->holds($user, $ability)) {
            return true;
        }

        return null;
    }
}
