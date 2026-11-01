<?php

namespace TelegramBotEssentials\Access\Listeners;

use Stancl\Tenancy\Events\TenantCreated;
use TelegramBotEssentials\Access\Services\RoleSeeder;
use TelegramBotEssentials\Essence\Models\Bot;

/** A new bot gets its default roles: a Bot is a tenant, so creating one fires TenantCreated. */
class SeedDefaultRoles
{
    public function __construct(private readonly RoleSeeder $seeder) {}

    public function handle(TenantCreated $event): void
    {
        if ($event->tenant instanceof Bot) {
            $this->seeder->seed($event->tenant);
        }
    }
}
