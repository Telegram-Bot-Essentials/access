<?php

namespace TelegramBotEssentials\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use TelegramBotEssentials\Access\Abilities;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * @property int $id
 * @property int $bot_id
 * @property string $name
 * @property list<string> $abilities
 */
class Role extends Model
{
    use BelongsToTenant;

    protected $table = 'access_roles';

    protected $fillable = ['bot_id', 'name', 'abilities'];

    protected $casts = [
        'abilities' => 'array',
    ];

    /** @return BelongsToMany<BotUser, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(BotUser::class, 'access_role_user', 'role_id', 'bot_user_id');
    }

    /** @return list<string> */
    public function abilityList(): array
    {
        $abilities = $this->getAttribute('abilities');

        return is_array($abilities) ? array_values(array_filter($abilities, 'is_string')) : [];
    }

    public function grants(string $ability): bool
    {
        $abilities = $this->abilityList();

        return in_array(Abilities::WILDCARD, $abilities, true) || in_array($ability, $abilities, true);
    }
}
