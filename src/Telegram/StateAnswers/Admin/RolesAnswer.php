<?php

namespace TelegramBotEssentials\Access\Telegram\StateAnswers\Admin;

use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Access\Permissions;
use TelegramBotEssentials\Access\Telegram\Features\Admin\RolesFeature;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Telegram\StateAnswers\StateAnswer;
use TelegramBotEssentials\Essence\Telegram\TelegramResponse;

/**
 * The typed half of the roles screens: a new role's name, a rename, and
 * the user to add to a role.
 */
class RolesAnswer extends StateAnswer
{
    protected string $type = RolesFeature::TYPE;

    protected ?string $permission = Permissions::MANAGE_ROLES;

    /**
     * @throws TelegramSDKException
     */
    public function create(): void
    {
        $name = $this->validName();
        if ($name === null) {
            return;
        }

        $role = Role::query()->create([
            'bot_id' => wHook()->bot()->getKey(),
            'name' => $name,
            'permissions' => [],
        ]);

        tbeLog('access')->audit('Created role '.$name, ['role_id' => $role->id]);

        $this->finish(__('tbe-access::roles.create.done', ['name' => $name]), RolesFeature::show($role));
    }

    /**
     * @throws TelegramSDKException
     */
    public function rename(Role $role): void
    {
        if (! accessManager()->canGrantRole(wHook()->user(), $role)) {
            $this->finish(__('tbe-access::roles.denied'), RolesFeature::show($role));

            return;
        }

        $name = $this->validName($role);
        if ($name === null) {
            return;
        }

        tbeLog('access')->audit('Renamed role '.$role->name.' to '.$name, ['role_id' => $role->id]);
        $role->name = $name;
        $role->save();

        $this->finish(__('tbe-access::roles.rename.done', ['name' => $name]), RolesFeature::show($role));
    }

    /**
     * @throws TelegramSDKException
     */
    public function addMember(Role $role): void
    {
        $target = $this->findUser($this->text());

        if ($target === null) {
            $this->reply(__('tbe-access::roles.members.text.notFound'));

            return;
        }

        if (! accessManager()->assign(wHook()->user(), $target, $role)) {
            $this->finish(__('tbe-access::roles.denied'), RolesFeature::members($role, 1));

            return;
        }

        tbeLog('access')->audit('Gave role '.$role->name.' to '.RolesFeature::peerId($target), [
            'role_id' => $role->id,
            'target_bot_user_id' => $target->id,
        ]);

        $this->finish(__('tbe-access::roles.members.text.added', ['user' => RolesFeature::userName($target)]), RolesFeature::members($role, 1));
    }

    /** A numeric Telegram id or an @username, among this bot's users. */
    private function findUser(string $input): ?BotUser
    {
        $query = BotUser::query()
            ->where('bot_id', wHook()->bot()->getKey())
            ->with('telegramUser');

        if (str_starts_with($input, '@')) {
            return $query->whereHas('telegramUser', fn ($telegramUser) => $telegramUser->where('username', ltrim($input, '@')))->first();
        }

        return ctype_digit($input) ? $query->where('telegram_user_peer_id', $input)->first() : null;
    }

    /**
     * The typed name, or null (after telling the user why) when it is empty,
     * too long, or taken by another role of this bot.
     *
     * @throws TelegramSDKException
     */
    private function validName(?Role $except = null): ?string
    {
        $name = $this->text();

        if ($name === '' || mb_strlen($name) > 64) {
            $this->reply(__('tbe-access::roles.create.invalid'));

            return null;
        }

        $taken = Role::query()
            ->where('bot_id', wHook()->bot()->getKey())
            ->where('name', $name)
            ->when($except, fn ($query) => $query->whereKeyNot($except?->id))
            ->exists();

        if ($taken) {
            $this->reply(__('tbe-access::roles.create.taken', ['name' => $name]));

            return null;
        }

        return $name;
    }

    private function text(): string
    {
        return trim((string) wHook()->update()->message?->text);
    }

    /**
     * @throws TelegramSDKException
     */
    private function reply(string $text): void
    {
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser?->getAttribute('peer_id'),
            'text' => $text,
            'reply_markup' => replyKeyboard(),
        ]);
    }

    /**
     * End the flow: clear the state, confirm, and put the screen the flow
     * started from back where it was.
     *
     * @throws TelegramSDKException
     */
    private function finish(string $text, TelegramResponse $screen): void
    {
        $messageMeta = $this->requireMessageMeta();
        wHook()->user()->changeState();
        $this->reply($text);
        $messageMeta->updateAndContinueAction($screen);
    }
}
