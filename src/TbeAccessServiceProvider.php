<?php

namespace TelegramBotEssentials\Access;

use Illuminate\Support\ServiceProvider;

class TbeAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind services/singletons your package needs here, e.g.:
        // $this->app->singleton(YourService::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'tbe-access');

        // CallbackQueries and StateAnswers register themselves here, in
        // boot() - list every class your package ships.
        // callbackQueryBus()->addCallbackQueries([
        //     ExampleQuery::class,
        // ]);
        //
        // stateAnswerBus()->addStateAnswers([
        //     ExampleAnswer::class,
        // ]);

        // ReplyKeys are different: essence has no directory-scan for a
        // companion's own ReplyKeys, only for its own built-ins and the
        // consuming app's app/Telegram/**. A companion's ReplyKey only
        // appears once the consuming app lists it in
        // config('tbe-essence.keyboard') - e.g.:
        //
        //   'keyboard' => [
        //       'member' => [[\TelegramBotEssentials\Access\Telegram\ReplyKeys\Member\ExampleKey::class]],
        //   ],
        //
        // Document that in your README rather than trying to register it
        // here - it won't take effect from this provider.

        // If you listen for essence's events, do it here too, e.g.:
        // botEventBus()->listen(BotUpdateReceived::class, YourListener::class);
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
