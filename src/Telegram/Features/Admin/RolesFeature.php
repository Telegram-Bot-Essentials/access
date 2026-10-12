<?php

namespace TelegramBotEssentials\Access\Telegram\Features\Admin;

use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Access\Permissions;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Support\Permission;
use TelegramBotEssentials\Essence\Telegram\TelegramResponse;

/**
 * Builds every roles screen. Buttons only render what the viewer may do;
 * the handlers re-check through AccessManager, since callback data can be
 * forged.
 */
class RolesFeature
{
    public const TYPE = 'ACCROLES';

    public const PER_PAGE = 8;

    public static function index(): TelegramResponse
    {
        $roles = Role::query()->withCount('users')->orderBy('name')->get();

        $replyMarkup = Keyboard::make()->inline();

        foreach ($roles as $role) {
            $replyMarkup->row([
                Keyboard::inlineButton([
                    'text' => __('tbe-access::roles.index.keys.role', [
                        'name' => $role->name,
                        'permissions' => count($role->permissionList()),
                        'members' => is_numeric($role->getAttribute('users_count')) ? (int) $role->getAttribute('users_count') : 0,
                    ]),
                    'callback_data' => encodeCallback(self::TYPE, 'show', [$role->id]),
                ]),
            ]);
        }

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-access::roles.index.keys.create'),
                'callback_data' => encodeCallback(self::TYPE, 'create'),
            ]),
        ]);

        return new TelegramResponse(
            text: $roles->isEmpty() ? __('tbe-access::roles.index.text.empty') : __('tbe-access::roles.index.text.list'),
            replyMarkup: $replyMarkup,
        );
    }

    public static function show(Role $role): TelegramResponse
    {
        $labels = array_map(fn (string $name) => '• '.self::label($name), $role->permissionList());

        $text = __('tbe-access::roles.show.text', [
            'name' => $role->name,
            'permissions' => $labels === [] ? __('tbe-access::roles.show.none') : implode("\n", $labels),
            'members' => $role->users()->count(),
        ]);

        $replyMarkup = Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton([
                    'text' => __('tbe-access::roles.show.keys.permissions'),
                    'callback_data' => encodeCallback(self::TYPE, 'permissions', [$role->id, 1]),
                ]),
                Keyboard::inlineButton([
                    'text' => __('tbe-access::roles.show.keys.members'),
                    'callback_data' => encodeCallback(self::TYPE, 'members', [$role->id, 1]),
                ]),
            ])
            ->row([
                Keyboard::inlineButton([
                    'text' => __('tbe-access::roles.show.keys.rename'),
                    'callback_data' => encodeCallback(self::TYPE, 'rename', [$role->id]),
                ]),
                Keyboard::inlineButton([
                    'text' => __('tbe-access::roles.show.keys.delete'),
                    'callback_data' => encodeCallback(self::TYPE, 'delete', [$role->id]),
                ]),
            ])
            ->row([self::backButton('index')]);

        return new TelegramResponse(text: $text, replyMarkup: $replyMarkup);
    }

    public static function confirmDelete(Role $role): TelegramResponse
    {
        return new TelegramResponse(
            text: __('tbe-access::roles.delete.text', ['name' => $role->name]),
            replyMarkup: Keyboard::make()->inline()->row([
                Keyboard::inlineButton([
                    'text' => __('tbe-access::roles.delete.keys.confirm'),
                    'callback_data' => encodeCallback(self::TYPE, 'confirmDelete', [$role->id]),
                ]),
                self::backButton('show', [$role->id]),
            ]),
        );
    }

    /** The permission checklist: "*" first, then every registered permission by group. */
    public static function permissions(Role $role, int $page): TelegramResponse
    {
        $all = self::catalog();
        $pages = max(1, (int) ceil(count($all) / self::PER_PAGE));
        $page = min(max(1, $page), $pages);
        $held = $role->permissionList();

        $replyMarkup = Keyboard::make()->inline();

        foreach (array_slice($all, ($page - 1) * self::PER_PAGE, self::PER_PAGE) as $name) {
            $replyMarkup->row([
                Keyboard::inlineButton([
                    'text' => (in_array($name, $held, true) ? '✅ ' : '⬜ ').self::label($name),
                    'callback_data' => encodeCallback(self::TYPE, 'toggle', [$role->id, self::hash($name), $page]),
                ]),
            ]);
        }

        self::pager($replyMarkup, 'permissions', $role->id, $page, $pages);
        $replyMarkup->row([self::backButton('show', [$role->id])]);

        return new TelegramResponse(
            text: __('tbe-access::roles.permissions.text', ['name' => $role->name]),
            replyMarkup: $replyMarkup,
        );
    }

    public static function members(Role $role, int $page): TelegramResponse
    {
        $members = $role->users()->with('telegramUser')->orderBy('bot_users.id')->paginate(perPage: self::PER_PAGE, page: $page);

        $replyMarkup = Keyboard::make()->inline();

        foreach ($members as $member) {
            $replyMarkup->row([
                Keyboard::inlineButton([
                    'text' => '❌ '.self::userName($member),
                    'callback_data' => encodeCallback(self::TYPE, 'removeMember', [$role->id, $member->id, $page]),
                ]),
            ]);
        }

        self::pager($replyMarkup, 'members', $role->id, $members->currentPage(), $members->lastPage());

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-access::roles.members.keys.add'),
                'callback_data' => encodeCallback(self::TYPE, 'addMember', [$role->id]),
            ]),
        ]);
        $replyMarkup->row([self::backButton('show', [$role->id])]);

        return new TelegramResponse(
            text: $members->isEmpty()
                ? __('tbe-access::roles.members.text.empty', ['name' => $role->name])
                : __('tbe-access::roles.members.text.list', ['name' => $role->name]),
            replyMarkup: $replyMarkup,
        );
    }

    /** A user's roles, from their user-management profile. */
    public static function userRoles(BotUser $target): TelegramResponse
    {
        $held = accessManager()->rolesOf($target)->pluck('id')->all();

        $replyMarkup = Keyboard::make()->inline();

        foreach (Role::query()->orderBy('name')->get() as $role) {
            $replyMarkup->row([
                Keyboard::inlineButton([
                    'text' => (in_array($role->id, $held, true) ? '✅ ' : '⬜ ').$role->name,
                    'callback_data' => encodeCallback(self::TYPE, 'toggleUserRole', [$target->id, $role->id]),
                ]),
            ]);
        }

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe::general.keys.back'),
                'callback_data' => encodeCallback('BOTUSERS', 'show', [$target->id]),
            ]),
        ]);

        return new TelegramResponse(
            text: __('tbe-access::roles.user.text', ['name' => self::userName($target)]),
            replyMarkup: $replyMarkup,
        );
    }

    /**
     * Every permission an owner can tick, wildcard first.
     *
     * @return list<string>
     */
    public static function catalog(): array
    {
        $names = [Permissions::WILDCARD];
        foreach (permissionRegistry()->grouped() as $permissions) {
            foreach (array_keys($permissions) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * A short, stable id for a permission in callback data. Registry order
     * depends on provider boot order, so a position would point at a
     * different permission once a package is added.
     */
    public static function hash(string $permission): string
    {
        return hash('crc32b', $permission);
    }

    public static function fromHash(string $hash): ?string
    {
        foreach (self::catalog() as $name) {
            if (self::hash($name) === $hash) {
                return $name;
            }
        }

        return null;
    }

    public static function label(string $permission): string
    {
        if ($permission === Permissions::WILDCARD) {
            return __('tbe-access::roles.permissions.wildcard');
        }

        return permissionRegistry()->get($permission)?->label() ?? $permission;
    }

    public static function userName(BotUser $user): string
    {
        $telegramUser = $user->telegramUser;
        $name = $telegramUser?->getAttribute('full_name');
        $username = $telegramUser?->getAttribute('username');

        $label = is_string($name) && $name !== '' ? $name : self::peerId($user);

        return is_string($username) && $username !== '' ? "{$label} (@{$username})" : $label;
    }

    public static function peerId(BotUser $user): string
    {
        $peerId = $user->getAttribute('telegram_user_peer_id');

        return is_int($peerId) || is_string($peerId) ? (string) $peerId : '?';
    }

    /** @param  array<int, int|string>  $params */
    private static function backButton(string $method, array $params = []): mixed
    {
        return Keyboard::inlineButton([
            'text' => __('tbe::general.keys.back'),
            'callback_data' => encodeCallback(self::TYPE, $method, $params),
        ]);
    }

    /** @param  Keyboard<array-key, mixed>  $replyMarkup */
    private static function pager(Keyboard $replyMarkup, string $method, int $roleId, int $page, int $pages): void
    {
        if ($pages <= 1) {
            return;
        }

        $row = [];
        if ($page > 1) {
            $row[] = Keyboard::inlineButton(['text' => '◀️', 'callback_data' => encodeCallback(self::TYPE, $method, [$roleId, $page - 1])]);
        }
        $row[] = Keyboard::inlineButton(['text' => "{$page}/{$pages}", 'callback_data' => encodeCallback(self::TYPE, $method, [$roleId, $page])]);
        if ($page < $pages) {
            $row[] = Keyboard::inlineButton(['text' => '▶️', 'callback_data' => encodeCallback(self::TYPE, $method, [$roleId, $page + 1])]);
        }

        $replyMarkup->row($row);
    }
}
