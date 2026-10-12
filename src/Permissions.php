<?php

namespace TelegramBotEssentials\Access;

/**
 * The permission names tbe-access itself defines.
 */
final class Permissions
{
    /** Grants every permission, including ones registered later. */
    public const WILDCARD = '*';

    /** Open the roles screens and give roles to users - bounded by what the holder has. */
    public const MANAGE_ROLES = 'access.roles.manage';
}
