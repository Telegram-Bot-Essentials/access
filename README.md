# telegram-bot-essentials/access

[![tests](https://github.com/telegram-bot-essentials/access/actions/workflows/tests.yml/badge.svg)](https://github.com/telegram-bot-essentials/access/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Template repository for a new `telegram-bot-essentials/*` companion package. Clone it via GitHub's "Use this template" (not a fork - you want fresh history), then follow **Renaming this** below. It's a real, working package as-is - `composer test` passes on a fresh clone - not a pile of `{{PLACEHOLDER}}` tokens.

## What's here

- `composer.json` wired for the ecosystem: essence as a dependency, the Pest/Pint/PHPStan/Testbench dev toolchain, `test`/`lint`/`format`/`analyse` scripts.
- `src/TbeAccessServiceProvider.php` - the real registration shape: migrations, translations, `callbackQueryBus()`/`stateAnswerBus()` registration, and a comment explaining why `ReplyKey`s are *not* registered here (see below).
- `src/Telegram/{CallbackQueries,StateAnswers,ReplyKeys,Commands,Features}/{Admin,Member}/` - the directory convention every companion package follows, so `essence`'s `tbe:make:*` generators drop files in the right place.
- One working example, `ExampleKey`, with its own test - proof the scaffold actually runs, not a placeholder to delete blindly.
- `tests/TestCase.php` extending essence's `Testing\TestCase` - `Http::fake()` by default, `makeBot()`/`makeBotUser()`/`postWebhookUpdate()`/`assertTelegramSent()` for free. See essence's own README for the full API.
- GitHub Actions CI: Pest across PHP 8.3/8.4/8.5 × Laravel 12/13, plus Pint and PHPStan (level max - no baseline needed for a package that starts clean).

## Renaming this

Find-and-replace these, in this order (the third depends on the first two):

| From | To | Where |
|---|---|---|
| `telegram-bot-essentials/access` | `telegram-bot-essentials/your-package` | `composer.json`, README/CHANGELOG |
| `TelegramBotEssentials\Access` | `TelegramBotEssentials\YourPackage` | every PHP file's namespace/`use` |
| `TbeAccessServiceProvider` | `TbeYourPackageServiceProvider` | `composer.json`'s `extra.laravel.providers`, the provider's filename and class, `tests/TestCase.php` |
| `tbe-access` | `tbe-your-package` | the translation namespace and publish tag in the provider, `lang/` usage |

Then:

- Update `composer.json`'s `description` and `authors`.
- Delete `ExampleKey.php`, its lang entries, and `ExampleKeyTest.php` once you've built your own (or keep it as a live reference while you get oriented).
- Delete any of the empty `.gitkeep`-only directories under `src/Telegram/**` you don't end up using.
- Run `composer test && composer lint && composer analyse` to confirm the rename didn't break anything.

## Testing conventions worth knowing up front

- **A companion's `ReplyKey`s are not auto-discovered.** Essence only directory-scans its own built-ins and the consuming app's `app/Telegram/**`; a companion's `ReplyKey` only reaches the bus once the consuming app lists it in `config('tbe-essence.keyboard')`. A test exercising one needs to register it explicitly first - see `ExampleKeyTest.php` for the one-line pattern (`replyKeyBus()->addReplyKey(...)`), which stands in for what the app's config would otherwise do.
- **`CallbackQuery`s and `StateAnswer`s *are* self-registered**, in the provider's `boot()` via `callbackQueryBus()->addCallbackQueries([...])`/`stateAnswerBus()->addStateAnswers([...])` - list yours there and essence's `Testing\TestCase` picks them up automatically once your provider is registered.
- **`postWebhookUpdate()` drives the real stack** - routing, `TelegramBotAuthentication`, the controller - not a shortcut. Outbound Telegram calls are faked automatically (`LaravelHttpClient` routes every SDK call through `Illuminate\Support\Facades\Http`, and `Http::fake()` runs in `setUp()`), so nothing ever reaches the real Telegram API.
- **`makeBot()` defaults `activated_until` ten years out.** The `bots` migration's `useCurrent()` means a bot created without one is immediately treated as expired.
- **Role-gated handlers need `makeBotUser()`** with an explicit `power`, pre-created at the same `peerId` you pass to `makeMessageUpdate()`/`makeCallbackQueryUpdate()` - otherwise the webhook auth flow auto-creates a fresh member-level user.

## Static analysis

PHPStan runs at level max with no baseline file - a new package should start clean and stay that way. `phpstan.neon.dist` does carry one narrow `ignoreErrors` entry for `wHook()->user()->telegramUser->peer_id`, an ecosystem-wide idiom (Larastan types a `belongsTo` as nullable even though it's backed by a `NOT NULL` foreign key); it goes away along with `ExampleKey.php`. If you inherit code with real pre-existing debt (e.g. porting from an older, unlinted package), generate a proper baseline the same way essence did:

```bash
composer analyse -- --generate-baseline=phpstan-baseline.neon
```

then add `- phpstan-baseline.neon` to `phpstan.neon.dist`'s `includes`. New code still has to be clean.

## See also

- [telegram-bot-essentials/essence](https://github.com/Telegram-Bot-Essentials/essence) - the core framework this depends on, including its own README's Testing section for the full `Testing\TestCase` API.
