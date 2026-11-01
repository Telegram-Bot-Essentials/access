# telegram-bot-essentials/access

Owner-managed roles for bots built on [telegram-bot-essentials/essence](https://github.com/Telegram-Bot-Essentials/essence).

Essence has no access model: a handler names a Laravel Gate ability (`protected ?string $ability`), and with no Gate rule every ability is denied, the bot owner included. This package is the Gate rule. It lets the owner hand abilities to other users through flat roles:

- A **role** is a name plus a set of ability names, per bot. Roles have no order and no inheritance, so "Support" and "Accountant" can overlap or not.
- A user can hold **several roles**; their access is the union.
- The **bot owner** and the configured **developer** (`tbe-essence.developer.peer_id`) can always do everything.
- Taking a role away takes its access away.

## How it plugs in

- It registers a `Gate::before` that answers for `BotUser`s only: owner or developer, or a role granting the ability (or `*`), passes; anything else is left to the app's other gates and, with none, denied. Web users (Telescope, Log Viewer, ...) are never answered here.
- The abilities on the checklist come from essence's `abilities()` catalog, read from the handlers themselves, so a new handler's ability shows up without being listed anywhere. This package adds:
  - `access.roles.manage`: open the roles screens and give roles to users.
  - `*`: everything, including abilities added later.
- It requires **user-management** and adds a **🛡 Roles** button to each user's profile there.

## Default roles

Every bot starts with the roles in `config/tbe-access.php` (publish with `php artisan vendor:publish --tag=tbe-access-config`):

```php
'default_roles' => [
    // the bot whose unique_id is tbe-essence.main.unique_id
    'main' => [
        'Admin' => ['*'],
        'Catalog' => ['shop.admin_panel', 'shop.catalog.manage'],
    ],
    // every other bot
    'sub' => [
        'Admin' => ['*'],
    ],
],
```

- A new bot gets them when it is created (a `Bot` is a stancl tenant, so `TenantCreated` fires).
- Existing bots get the ones they lack from `php artisan tbe:access:sync-roles` (`--bot=<id|unique_id>` to limit it). It is safe to run on every deploy.
- A role that already exists is never changed, so the owner's edits stay.

## Delegation

Role management can be handed out by granting `access.roles.manage`. A delegate:

- can grant only the abilities they hold themselves, and never `*` (unless they hold it);
- can give, take, edit or delete only roles made entirely of abilities they hold;
- can't change their own roles;
- can't change the owner's or the developer's roles.

The handlers enforce these rules themselves; callback data is never trusted.

## Install

```bash
composer require telegram-bot-essentials/access
php artisan migrate
php artisan tbe:access:sync-roles
```

Companion reply keys aren't auto-registered, so add the Roles key to a menu:

```php
// config/tbe-essence.php
'keyboard' => [
    'menus' => [
        'admin' => [
            // ...
            [\TelegramBotEssentials\Access\Telegram\ReplyKeys\Admin\RolesKey::class],
        ],
    ],
],
```

## Naming abilities in your app or package

```php
// every handler of that feature: reply key, callback queries, state answers, forms
protected ?string $ability = 'shop.panels.manage';
```

Label it in an `abilities` translation file, keyed by the full name: `lang/<locale>/abilities.php` in the app, or the same file in a package's translation namespace.

```php
return [
    'shop.panels.manage' => 'Manage panels',
];
```

An ability that only code checks (`hasAccess('shop.reports.export')`) is not on any handler, so declare it in a service provider's `boot()` to put it on the checklist: `abilities()->declare('shop.reports.export');`.
