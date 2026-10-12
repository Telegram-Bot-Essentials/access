<?php

namespace TelegramBotEssentials\Access\Telegram\CallbackQueries\Admin;

use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Access\Permissions;
use TelegramBotEssentials\Access\Telegram\Features\Admin\RolesFeature;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Telegram\CallbackQueries\CallbackQuery;

class RolesQuery extends CallbackQuery
{
    protected string $type = RolesFeature::TYPE;

    protected ?string $permission = Permissions::MANAGE_ROLES;

    /**
     * @throws TelegramSDKException
     */
    public function index(): void
    {
        RolesFeature::index()->update();
    }

    /**
     * @throws TelegramSDKException
     */
    public function show(Role $role): void
    {
        RolesFeature::show($role)->update();
    }

    /**
     * @throws TelegramSDKException
     */
    public function permissions(Role $role, int $page = 1): void
    {
        RolesFeature::permissions($role, $page)->update();
    }

    /**
     * @throws TelegramSDKException
     */
    public function toggle(Role $role, string $hash, int $page = 1): void
    {
        $permission = RolesFeature::fromHash($hash);

        if ($permission === null || ! accessManager()->togglePermission(wHook()->user(), $role, $permission)) {
            $this->deny();

            return;
        }

        $this->audit('Toggled '.$permission.' on role '.$role->name, ['role_id' => $role->id, 'permission' => $permission]);

        RolesFeature::permissions($role, $page)->update();
    }

    /**
     * @throws TelegramSDKException
     */
    public function members(Role $role, int $page = 1): void
    {
        RolesFeature::members($role, $page)->update();
    }

    /**
     * @throws TelegramSDKException
     */
    public function removeMember(Role $role, BotUser $botUser, int $page = 1): void
    {
        if (! accessManager()->revoke(wHook()->user(), $botUser, $role)) {
            $this->deny();

            return;
        }

        $this->audit('Removed '.RolesFeature::peerId($botUser).' from role '.$role->name, ['role_id' => $role->id, 'target_bot_user_id' => $botUser->id]);

        RolesFeature::members($role, $page)->update();
    }

    /**
     * @throws TelegramSDKException
     * @throws BindingResolutionException
     * @throws LogicException
     */
    public function addMember(Role $role): void
    {
        if (! accessManager()->canGrantRole(wHook()->user(), $role)) {
            $this->deny();

            return;
        }

        $this->ask('addMember', __('tbe-access::roles.members.text.enter'), __('tbe-access::roles.members.lock'), ['role' => $role->id]);
    }

    /**
     * @throws TelegramSDKException
     * @throws BindingResolutionException
     * @throws LogicException
     */
    public function create(): void
    {
        $this->ask('create', __('tbe-access::roles.create.enter'), __('tbe-access::roles.create.lock'));
    }

    /**
     * @throws TelegramSDKException
     * @throws BindingResolutionException
     * @throws LogicException
     */
    public function rename(Role $role): void
    {
        if (! accessManager()->canGrantRole(wHook()->user(), $role)) {
            $this->deny();

            return;
        }

        $this->ask('rename', __('tbe-access::roles.rename.enter', ['name' => $role->name]), __('tbe-access::roles.rename.lock'), ['role' => $role->id]);
    }

    /**
     * @throws TelegramSDKException
     */
    public function delete(Role $role): void
    {
        if (! accessManager()->canGrantRole(wHook()->user(), $role)) {
            $this->deny();

            return;
        }

        RolesFeature::confirmDelete($role)->update();
    }

    /**
     * @throws TelegramSDKException
     */
    public function confirmDelete(Role $role): void
    {
        if (! accessManager()->canGrantRole(wHook()->user(), $role)) {
            $this->deny();

            return;
        }

        $this->audit('Deleted role '.$role->name, ['role_id' => $role->id, 'permissions' => $role->permissionList()]);
        $role->delete();

        RolesFeature::index()->answer(__('tbe-access::roles.delete.done'))->update();
    }

    /**
     * @throws TelegramSDKException
     */
    public function userRoles(BotUser $botUser): void
    {
        RolesFeature::userRoles($botUser)->update();
    }

    /**
     * @throws TelegramSDKException
     */
    public function toggleUserRole(BotUser $botUser, Role $role): void
    {
        $actor = wHook()->user();
        $has = accessManager()->rolesOf($botUser)->contains('id', $role->id);
        $done = $has ? accessManager()->revoke($actor, $botUser, $role) : accessManager()->assign($actor, $botUser, $role);

        if (! $done) {
            $this->deny();

            return;
        }

        $this->audit(($has ? 'Removed ' : 'Gave ').'role '.$role->name.($has ? ' from ' : ' to ').RolesFeature::peerId($botUser), [
            'role_id' => $role->id,
            'target_bot_user_id' => $botUser->id,
        ]);

        RolesFeature::userRoles($botUser)->update();
    }

    /**
     * @param  array<string, int>  $params
     *
     * @throws TelegramSDKException
     * @throws BindingResolutionException
     * @throws LogicException
     */
    private function ask(string $method, string $prompt, string $lock, array $params = []): void
    {
        $messageMeta = MessageMeta::makeWithCurrentMessage();
        $messageMeta->lockAction($lock);
        wHook()->conversation()->changeState(encodeAnswerState($this->type, $method, ['message_meta_id' => $messageMeta->id, ...$params]));
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser?->getAttribute('peer_id'),
            'text' => $prompt,
            'reply_markup' => replyKeyboard(),
        ]);
        $this->answer();
    }

    private function deny(): void
    {
        $callbackQuery = wHook()->update()->callbackQuery;
        if ($callbackQuery === null) {
            return;
        }

        try {
            wHook()->api()->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->id,
                'text' => __('tbe-access::roles.denied'),
                'show_alert' => true,
            ]);
        } catch (TelegramSDKException) {
        }
    }

    /** @param  array<string, mixed>  $context */
    private function audit(string $message, array $context): void
    {
        tbeLog('access')->audit($message, $context);
    }
}
