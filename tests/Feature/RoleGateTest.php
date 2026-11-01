<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use TelegramBotEssentials\Access\Abilities;
use TelegramBotEssentials\Access\Models\Role;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Telegram\Commands\Command;

/*
 * Installing tbe-access lets roles answer essence's Gate checks: a role's
 * abilities decide, and the owner and developer hold everything.
 */

function giveRole(BotUser $user, array $abilities, string $name = 'Role'): Role
{
    $role = Role::query()->create([
        'bot_id' => $user->bot_id,
        'name' => $name,
        'abilities' => $abilities,
    ]);
    $role->users()->attach($user->id);
    accessManager()->forget($user);

    return $role;
}

function allows(BotUser $user, string $ability): bool
{
    return Gate::forUser($user)->allows($ability);
}

class GateReportsCommand extends Command
{
    public static int $runs = 0;

    protected string $name = 'gate_reports';

    protected ?string $ability = 'reports.view';

    public function handle(): ?bool
    {
        self::$runs++;

        return true;
    }
}

beforeEach(function () {
    $this->bot = $this->makeBot();
    $this->user = $this->makeBotUser($this->bot, 701);
});

it('denies an ability no role grants', function () {
    expect(allows($this->user, 'reports.view'))->toBeFalse();
});

it('takes access away with the role', function () {
    $role = giveRole($this->user, ['reports.view']);
    $role->users()->detach($this->user->id);
    accessManager()->forget($this->user);

    expect(allows($this->user, 'reports.view'))->toBeFalse();
});

it('grants exactly the abilities a role holds', function () {
    giveRole($this->user, ['reports.view']);

    expect(allows($this->user, 'reports.view'))->toBeTrue()
        ->and(allows($this->user, 'panels.manage'))->toBeFalse();
});

it('unions the abilities of several roles', function () {
    giveRole($this->user, ['reports.view'], 'Reports');
    giveRole($this->user, ['panels.manage'], 'Panels');

    expect(allows($this->user, 'reports.view'))->toBeTrue()
        ->and(allows($this->user, 'panels.manage'))->toBeTrue();
});

it('lets the wildcard through everything', function () {
    giveRole($this->user, [Abilities::WILDCARD]);

    expect(allows($this->user, 'anything.at.all'))->toBeTrue();
});

it('lets the owner and the developer through with no roles', function () {
    $owner = BotUser::factory()->create([
        'bot_id' => $this->bot->id,
        'telegram_user_peer_id' => $this->bot->bot_owner_peer_id,
    ]);
    config(['tbe-essence.developer.peer_id' => '777']);
    $developer = $this->makeBotUser($this->bot, 777);

    expect(allows($owner, 'reports.view'))->toBeTrue()
        ->and(allows($developer, 'reports.view'))->toBeTrue();
});

it('never reads another bot\'s roles', function () {
    $otherBot = $this->makeBot();
    $foreign = Role::query()->create(['bot_id' => $otherBot->id, 'name' => 'Foreign', 'abilities' => ['reports.view']]);
    $foreign->users()->attach($this->user->id);

    expect(allows($this->user, 'reports.view'))->toBeFalse();
});

it('leaves users that are not bot users to the app\'s own gates', function () {
    Gate::define('viewTelescope', fn (?object $user = null) => false);

    expect(Gate::forUser(new stdClass)->allows('viewTelescope'))->toBeFalse();
});

it('gates handlers through the buses', function () {
    commandBus()->addCommand(GateReportsCommand::class);
    GateReportsCommand::$runs = 0;

    $this->postWebhookUpdate($this->bot, $this->makeMessageUpdate('/gate_reports', peerId: 701))->assertOk();
    giveRole($this->user, ['reports.view']);
    $this->postWebhookUpdate($this->bot, $this->makeMessageUpdate('/gate_reports', peerId: 701))->assertOk();

    expect(GateReportsCommand::$runs)->toBe(1);
});

it('lists its own ability in essence\'s catalog, with a label', function () {
    expect(abilities()->has(Abilities::MANAGE_ROLES))->toBeTrue()
        ->and(abilities()->label(Abilities::MANAGE_ROLES))->toBe('Manage roles and give them to users');
});
