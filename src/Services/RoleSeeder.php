<?php

namespace TelegramBotEssentials\Access\Services;

use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Essence\Models\Bot;

/**
 * Creates a bot's default roles (tbe-access.default_roles). Only missing
 * roles are created: an existing role of the same name keeps the
 * abilities it has, whatever the config says now.
 */
class RoleSeeder
{
    /** @return list<string> the names of the roles it created */
    public function seed(Bot $bot): array
    {
        $created = [];

        foreach ($this->defaultsFor($bot) as $name => $abilities) {
            $role = Role::withoutGlobalScopes()->firstOrCreate(
                ['bot_id' => $bot->getKey(), 'name' => $name],
                ['abilities' => $abilities],
            );

            if ($role->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        return $created;
    }

    public function isMainBot(Bot $bot): bool
    {
        $mainId = config('tbe-essence.main.unique_id');

        return is_string($mainId) && $mainId !== '' && $bot->getAttribute('unique_id') === $mainId;
    }

    /** @return array<string, list<string>> */
    public function defaultsFor(Bot $bot): array
    {
        $defaults = config('tbe-access.default_roles.'.($this->isMainBot($bot) ? 'main' : 'sub'), []);

        $roles = [];
        foreach (is_array($defaults) ? $defaults : [] as $name => $abilities) {
            if (is_string($name) && $name !== '' && is_array($abilities)) {
                $roles[mb_substr($name, 0, 64)] = array_values(array_unique(array_filter($abilities, 'is_string')));
            }
        }

        return $roles;
    }
}
