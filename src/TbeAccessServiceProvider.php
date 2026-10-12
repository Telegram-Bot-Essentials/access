<?php

namespace TelegramBotEssentials\Access;

use Illuminate\Support\ServiceProvider;
use TelegramBotEssentials\Access\Services\AccessManager;
use TelegramBotEssentials\Access\Support\RoleAuthorizer;
use TelegramBotEssentials\Access\Telegram\CallbackQueries\Admin\RolesQuery;
use TelegramBotEssentials\Access\Telegram\Features\Admin\RolesFeature;
use TelegramBotEssentials\Access\Telegram\StateAnswers\Admin\RolesAnswer;
use TelegramBotEssentials\Essence\Contracts\AuthorizesAccess;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\UserManagement\DTOs\UserSection;
use TelegramBotEssentials\UserManagement\Enums\SectionMode;

class TbeAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once __DIR__.'/helpers.php';

        // Stateless: a user's roles are cached on the BotUser instance.
        $this->app->singleton(AccessManager::class, fn () => new AccessManager);

        // Swaps essence's owner-only default for roles, for every bus at once.
        $this->app->bind(AuthorizesAccess::class, RoleAuthorizer::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'tbe-access');

        permissionRegistry()->register(
            Permissions::MANAGE_ROLES,
            fn () => __('tbe-access::permissions.manage_roles'),
            'access',
        );

        callbackQueryBus()->addCallbackQueries([
            RolesQuery::class,
        ]);

        stateAnswerBus()->addStateAnswers([
            RolesAnswer::class,
        ]);

        // RolesKey is a companion ReplyKey, so the consuming app lists it in
        // config('tbe-essence.keyboard.menus.admin') - see the README.

        $this->registerUserSection();
    }

    /**
     * A "Roles" button on each user's user-management profile, shown only
     * to a viewer who may change that user's roles - never on their own
     * profile, nor on the owner's or the developer's.
     */
    private function registerUserSection(): void
    {
        userManagementSections()->addSection(new UserSection(
            key: 'access_roles',
            order: 5,
            mode: SectionMode::BUTTON,
            label: fn (BotUser $user) => __('tbe-access::roles.user.section', [
                'roles' => accessManager()->rolesOf($user)->pluck('name')->implode(', ') ?: __('tbe-access::roles.user.noRoles'),
            ]),
            target: fn (BotUser $user) => encodeCallback(RolesFeature::TYPE, 'userRoles', [$user->id]),
            active: fn (BotUser $user) => wHook()->check() && accessManager()->canChangeRolesOf(wHook()->user(), $user),
        ));
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../lang' => resource_path('lang/vendor/tbe-access'),
            ], 'tbe-access-translations');
        }
    }
}
