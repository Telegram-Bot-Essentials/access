<?php

namespace TelegramBotEssentials\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use TelegramBotEssentials\Access\Permissions;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * @property int $id
 * @property int $bot_id
 * @property string $name
 * @property list<string> $permissions
 */
class Role extends Model
{
    use BelongsToTenant;

    protected $table = 'access_roles';

    protected $fillable = ['bot_id', 'name', 'permissions'];

    protected $casts = [
        'permissions' => 'array',
    ];

    /** @return BelongsToMany<BotUser, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(BotUser::class, 'access_role_user', 'role_id', 'bot_user_id');
    }

    /** @return list<string> */
    public function permissionList(): array
    {
        $permissions = $this->getAttribute('permissions');

        return is_array($permissions) ? array_values(array_filter($permissions, 'is_string')) : [];
    }

    public function grants(string $permission): bool
    {
        $permissions = $this->permissionList();

        return in_array(Permissions::WILDCARD, $permissions, true) || in_array($permission, $permissions, true);
    }
}
