<?php

namespace TelegramBotEssentials\Access\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Access\Abilities;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * Who holds what, and who may hand what out.
 *
 * A user's roles are cached on the model instance (as the accessRoles
 * relation), never in a static: under Octane a static would outlive the
 * request. forget() drops the cache after a change.
 *
 * Escalation rules - every mutating handler goes through the methods
 * below, since callback data is client-supplied:
 *  - only the owner, the developer or a wildcard holder may grant "*";
 *  - anyone else may grant only abilities they hold themselves;
 *  - nobody changes their own roles;
 *  - nobody changes the roles of the owner or the developer.
 */
class AccessManager
{
    /** @return Collection<int, Role> */
    public function rolesOf(BotUser $user): Collection
    {
        if (! $user->relationLoaded('accessRoles')) {
            $roleIds = DB::table('access_role_user')->where('bot_user_id', $user->getKey())->pluck('role_id');

            // Filtered by the user's bot explicitly: the tenant scope only
            // applies once tenancy is initialised, and keyboards and jobs
            // are built outside a webhook too.
            $user->setRelation('accessRoles', Role::withoutGlobalScopes()
                ->where('bot_id', $user->getAttribute('bot_id'))
                ->whereIn('id', $roleIds)
                ->orderBy('name')
                ->get());
        }

        /** @var Collection<int, Role> $roles */
        $roles = $user->getRelation('accessRoles');

        return $roles;
    }

    public function forget(BotUser $user): void
    {
        $user->unsetRelation('accessRoles');
    }

    /** @return list<string> */
    public function abilitiesOf(BotUser $user): array
    {
        $abilities = [];
        foreach ($this->rolesOf($user) as $role) {
            array_push($abilities, ...$role->abilityList());
        }

        return array_values(array_unique($abilities));
    }

    public function holds(BotUser $user, string $ability): bool
    {
        $abilities = $this->abilitiesOf($user);

        return in_array(Abilities::WILDCARD, $abilities, true) || in_array($ability, $abilities, true);
    }

    /** The bot's owner or the configured developer: they hold everything, with or without roles. */
    public function isSuperuser(BotUser $user): bool
    {
        $peerId = $user->getAttribute('telegram_user_peer_id');
        if (! is_int($peerId) && ! is_string($peerId)) {
            return false;
        }
        $peerId = (string) $peerId;

        $developer = config('tbe-essence.developer.peer_id');
        if ((is_int($developer) || is_string($developer)) && (string) $developer !== '' && (string) $developer === $peerId) {
            return true;
        }

        $owner = $user->bot?->getAttribute('bot_owner_peer_id');

        return (is_int($owner) || is_string($owner)) && (string) $owner === $peerId;
    }

    public function canManage(BotUser $actor): bool
    {
        return $this->isSuperuser($actor) || $this->holds($actor, Abilities::MANAGE_ROLES);
    }

    public function canGrant(BotUser $actor, string $ability): bool
    {
        if ($this->isSuperuser($actor) || $this->holds($actor, Abilities::WILDCARD)) {
            return true;
        }

        return $ability !== Abilities::WILDCARD && $this->holds($actor, $ability);
    }

    /** Whether $actor could grant everything $role holds - required to give, take, edit or delete it. */
    public function canGrantRole(BotUser $actor, Role $role): bool
    {
        if (! $this->canManage($actor) || ! $this->sameBot($actor, $role->bot_id)) {
            return false;
        }

        foreach ($role->abilityList() as $ability) {
            if (! $this->canGrant($actor, $ability)) {
                return false;
            }
        }

        return true;
    }

    public function canChangeRolesOf(BotUser $actor, BotUser $target): bool
    {
        return $this->canManage($actor)
            && $actor->getKey() !== $target->getKey()
            && $this->sameBot($actor, $target->getAttribute('bot_id'))
            && ! $this->isSuperuser($target);
    }

    public function assign(BotUser $actor, BotUser $target, Role $role): bool
    {
        if (! $this->canChangeRolesOf($actor, $target) || ! $this->canGrantRole($actor, $role)) {
            return false;
        }

        $role->users()->syncWithoutDetaching([$target->getKey()]);
        $this->forget($target);

        return true;
    }

    public function revoke(BotUser $actor, BotUser $target, Role $role): bool
    {
        if (! $this->canChangeRolesOf($actor, $target) || ! $this->canGrantRole($actor, $role)) {
            return false;
        }

        $role->users()->detach($target->getKey());
        $this->forget($target);

        return true;
    }

    /** Add or remove one ability on a role. */
    public function toggleAbility(BotUser $actor, Role $role, string $ability): bool
    {
        if (! $this->canGrantRole($actor, $role) || ! $this->canGrant($actor, $ability)) {
            return false;
        }

        $abilities = $role->abilityList();
        $role->abilities = in_array($ability, $abilities, true)
            ? array_values(array_diff($abilities, [$ability]))
            : [...$abilities, $ability];
        $role->save();

        return true;
    }

    private function sameBot(BotUser $actor, mixed $botId): bool
    {
        $actorBotId = $actor->getAttribute('bot_id');

        return is_numeric($actorBotId) && is_numeric($botId) && (int) $actorBotId === (int) $botId;
    }
}
