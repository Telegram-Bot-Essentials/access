<?php

namespace TelegramBotEssentials\Access\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Essence\Contracts\RecipientGroups;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Models\BotUser;

/** Roles, as groups other packages can address without knowing about roles. */
class RoleRecipientGroups implements RecipientGroups
{
    public function options(Bot $bot): array
    {
        $options = [];

        foreach ($this->roles($bot)->orderBy('name')->get() as $role) {
            $options[(string) $role->getKey()] = $role->name;
        }

        return $options;
    }

    public function members(Bot $bot, array $ids): Collection
    {
        $roleIds = array_values(array_filter($ids, 'is_numeric'));

        if ($roleIds === []) {
            return collect();
        }

        return BotUser::query()
            ->where('bot_id', $bot->getKey())
            ->where('has_private_chat', true)
            ->whereIn('id', DB::table('access_role_user')
                ->select('bot_user_id')
                ->whereIn('role_id', $this->roles($bot)->whereIn('id', $roleIds)->select('id')))
            ->get();
    }

    /** By the given bot explicitly: the tenant scope only applies once tenancy is initialised. */
    private function roles(Bot $bot): Builder
    {
        return Role::withoutGlobalScopes()->where('bot_id', $bot->getKey());
    }
}
