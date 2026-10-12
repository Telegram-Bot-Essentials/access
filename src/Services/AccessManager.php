<?php

namespace TelegramBotEssentials\Access\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Access\Permissions;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Support\DefaultAuthorizer;

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
 *  - anyone else may grant only permissions they hold themselves;
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
    public function permissionsOf(BotUser $user): array
    {
        $permissions = [];
        foreach ($this->rolesOf($user) as $role) {
            array_push($permissions, ...$role->permissionList());
        }

        return array_values(array_unique($permissions));
    }

    public function holds(BotUser $user, string $permission): bool
    {
        $permissions = $this->permissionsOf($user);

        return in_array(Permissions::WILDCARD, $permissions, true) || in_array($permission, $permissions, true);
    }

    public function isSuperuser(BotUser $user): bool
    {
        return app(DefaultAuthorizer::class)->isOwnerOrDeveloper($user);
    }

    public function canManage(BotUser $actor): bool
    {
        return $this->isSuperuser($actor) || $this->holds($actor, Permissions::MANAGE_ROLES);
    }

    public function canGrant(BotUser $actor, string $permission): bool
    {
        if ($this->isSuperuser($actor) || $this->holds($actor, Permissions::WILDCARD)) {
            return true;
        }

        return $permission !== Permissions::WILDCARD && $this->holds($actor, $permission);
    }

    /** Whether $actor could grant everything $role holds - required to give, take, edit or delete it. */
    public function canGrantRole(BotUser $actor, Role $role): bool
    {
        if (! $this->canManage($actor) || ! $this->sameBot($actor, $role->bot_id)) {
            return false;
        }

        foreach ($role->permissionList() as $permission) {
            if (! $this->canGrant($actor, $permission)) {
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

    /** Add or remove one permission on a role. */
    public function togglePermission(BotUser $actor, Role $role, string $permission): bool
    {
        if (! $this->canGrantRole($actor, $role) || ! $this->canGrant($actor, $permission)) {
            return false;
        }

        $permissions = $role->permissionList();
        $role->permissions = in_array($permission, $permissions, true)
            ? array_values(array_diff($permissions, [$permission]))
            : [...$permissions, $permission];
        $role->save();

        return true;
    }

    private function sameBot(BotUser $actor, mixed $botId): bool
    {
        $actorBotId = $actor->getAttribute('bot_id');

        return is_numeric($actorBotId) && is_numeric($botId) && (int) $actorBotId === (int) $botId;
    }
}
