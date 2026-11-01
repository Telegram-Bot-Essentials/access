<?php

namespace TelegramBotEssentials\Access;

/**
 * The ability names tbe-access itself defines.
 */
final class Abilities
{
    /** Grants every ability, including ones added later. */
    public const WILDCARD = '*';

    /** Open the roles screens and give roles to users - bounded by what the holder has. */
    public const MANAGE_ROLES = 'access.roles.manage';
}
