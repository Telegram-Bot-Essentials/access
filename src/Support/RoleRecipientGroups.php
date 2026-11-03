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
            $name = $role->getAttribute('name');
            $id = $role->getKey();

            if (is_string($name) && is_scalar($id)) {
                $options[(string) $id] = $name;
            }
        }

        return $options;
    }

    public function members(Bot $bot, array $ids): Collection
    {
        $roleIds = array_values(array_filter($ids, 'is_numeric'));

        if ($roleIds === []) {
            return new Collection;
        }

        return new Collection(BotUser::query()
            ->where('bot_id', $bot->getKey())
            ->where('has_private_chat', true)
            ->whereIn('id', DB::table('access_role_user')
                ->select('bot_user_id')
                ->whereIn('role_id', $this->roles($bot)->whereIn('id', $roleIds)->select('id')))
            ->get()
            ->all());
    }

    /**
     * By the given bot explicitly: the tenant scope only applies once tenancy is initialised.
     *
     * @return Builder<Role>
     */
    private function roles(Bot $bot): Builder
    {
        return Role::withoutGlobalScopes()->where('bot_id', $bot->getKey());
    }
}
