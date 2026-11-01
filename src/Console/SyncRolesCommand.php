<?php

namespace TelegramBotEssentials\Access\Console;

use Illuminate\Console\Command;
use TelegramBotEssentials\Access\Services\RoleSeeder;
use TelegramBotEssentials\Essence\Models\Bot;

/**
 * Gives existing bots the default roles they are missing. New bots get
 * them on creation; this covers bots made before tbe-access was installed
 * or before a role was added to the config. Safe to run on every deploy.
 */
class SyncRolesCommand extends Command
{
    protected $signature = 'tbe:access:sync-roles
        {--bot=* : Only these bots (id or unique_id)}';

    protected $description = 'Create the configured default roles on bots that lack them';

    public function handle(RoleSeeder $seeder): int
    {
        /** @var list<string> $only */
        $only = array_values(array_filter((array) $this->option('bot'), 'is_string'));

        $bots = Bot::query()
            ->when($only !== [], fn ($query) => $query->where(fn ($query) => $query
                ->whereIn('id', array_filter($only, 'ctype_digit'))
                ->orWhereIn('unique_id', $only)))
            ->orderBy('id')
            ->get();

        $total = 0;
        foreach ($bots as $bot) {
            if (! $bot instanceof Bot) {
                continue;
            }

            $created = $seeder->seed($bot);
            $total += count($created);

            if ($created !== []) {
                $id = $bot->getKey();
                $uniqueId = $bot->getAttribute('unique_id');
                $this->line(sprintf(
                    'Bot %s (%s): created %s',
                    is_int($id) || is_string($id) ? $id : '?',
                    is_string($uniqueId) ? $uniqueId : '?',
                    implode(', ', $created),
                ));
            }
        }

        $this->info("Created {$total} role(s) on {$bots->count()} bot(s).");

        return self::SUCCESS;
    }
}
