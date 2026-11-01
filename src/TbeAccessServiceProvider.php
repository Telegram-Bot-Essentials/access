<?php

namespace TelegramBotEssentials\Access;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events\TenantCreated;
use TelegramBotEssentials\Access\Console\SyncRolesCommand;
use TelegramBotEssentials\Access\Listeners\SeedDefaultRoles;
use TelegramBotEssentials\Access\Services\AccessManager;
use TelegramBotEssentials\Access\Services\RoleSeeder;
use TelegramBotEssentials\Access\Support\RoleGate;
use TelegramBotEssentials\Access\Telegram\CallbackQueries\Admin\RolesQuery;
use TelegramBotEssentials\Access\Telegram\Features\Admin\RolesFeature;
use TelegramBotEssentials\Access\Telegram\StateAnswers\Admin\RolesAnswer;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\UserManagement\DTOs\UserSection;
use TelegramBotEssentials\UserManagement\Enums\SectionMode;

class TbeAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once __DIR__.'/helpers.php';

        $this->mergeConfigFrom(__DIR__.'/../config/tbe-access.php', 'tbe-access');

        // Stateless: a user's roles are cached on the BotUser instance.
        $this->app->singleton(AccessManager::class, fn () => new AccessManager);

        $this->app->singleton(RoleSeeder::class, fn () => new RoleSeeder);
    }

    public function boot(): void
    {
        $this->registerPublishing();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'tbe-access');

        // Roles decide every ability essence asks the Gate about, for every
        // bus at once.
        Gate::before(fn (mixed $user, string $ability) => app(RoleGate::class)->before($user, $ability));

        // A Bot is a stancl tenant: creating one fires TenantCreated.
        Event::listen(TenantCreated::class, SeedDefaultRoles::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncRolesCommand::class]);
        }

        callbackQueryBus()->addCallbackQueries([
            RolesQuery::class,
        ]);

        stateAnswerBus()->addStateAnswers([
            RolesAnswer::class,
        ]);

        // RolesKey is a companion ReplyKey, so the consuming app lists it in
        // a menu in config('tbe-essence.keyboard.menus') - see the README.

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
                __DIR__.'/../config/tbe-access.php' => config_path('tbe-access.php'),
            ], 'tbe-access-config');

            $this->publishes([
                __DIR__.'/../lang' => resource_path('lang/vendor/tbe-access'),
            ], 'tbe-access-translations');
        }
    }
}
