<?php

namespace TelegramBotEssentials\Access\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Stancl\Tenancy\Database\TenantScope;
use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Access\Services\AccessManager;
use TelegramBotEssentials\Essence\Contracts\AuthorizesAccess;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * Access by roles:
 *
 *  - no permission (null) is public;
 *  - the bot owner and the developer may use anything, checked before any
 *    role table is read;
 *  - a named permission needs a role that grants it (or "*").
 */
class RoleAuthorizer implements AuthorizesAccess
{
    public function __construct(private readonly AccessManager $access) {}

    public function allows(BotUser $user, ?string $permission): bool
    {
        if ($permission === null || $this->access->isSuperuser($user)) {
            return true;
        }

        return $this->access->holds($user, $permission);
    }

    public function usersWith(Bot $bot, string $permission): Builder
    {
        // A role holds a handful of names in a JSON column, so the matching
        // roles are picked in PHP rather than with database-specific JSON SQL.
        $roleIds = Role::withoutGlobalScopes()
            ->where('bot_id', $bot->getKey())
            ->get()
            ->filter(fn (Role $role) => $role->grants($permission))
            ->modelKeys();

        return BotUser::withoutGlobalScope(TenantScope::class)
            ->where('bot_id', $bot->getKey())
            ->whereIn('id', fn (QueryBuilder $query) => $query
                ->select('bot_user_id')
                ->from('access_role_user')
                ->whereIn('role_id', $roleIds));
    }
}
