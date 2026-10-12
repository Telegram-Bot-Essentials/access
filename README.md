# telegram-bot-essentials/access

Owner-managed roles for bots built on [telegram-bot-essentials/essence](https://github.com/Telegram-Bot-Essentials/essence).

Without this package, essence lets only the bot owner and the developer use anything that asks for a permission. Installing it lets the owner grant permissions to other users through flat roles:

- A **role** is a name plus a set of permission names, per bot. Roles have no order and no inheritance, so "Support" and "Accountant" can overlap or not.
- A user can hold **several roles**; their access is the union.
- The **bot owner** and the configured **developer** can always do everything.
- Taking a role away takes its access away.

## How it plugs in

- It rebinds essence's `AuthorizesAccess` contract with `RoleAuthorizer`, so every bus (commands, callbacks, reply keys, state answers, forms, menus) checks roles.
- Permissions come from essence's `permissionRegistry()`, where each package registers its own names. This package adds:
  - `access.roles.manage`: open the roles screens and give roles to users.
  - `*`: everything, including permissions registered later.
- It requires **user-management** and adds a **🛡 Roles** button to each user's profile there.

## Delegation

Role management can be handed out by granting `access.roles.manage`. A delegate:

- can grant only the permissions they hold themselves, and never `*` (unless they hold it);
- can give, take, edit or delete only roles made entirely of permissions they hold;
- can't change their own roles;
- can't change the owner's or the developer's roles.

The handlers enforce these rules themselves; callback data is never trusted.

## Install

```bash
composer require telegram-bot-essentials/access
php artisan migrate
```

Until roles are given out, only the owner and the developer can open anything that asks for a permission. The owner gives others access from the Roles screens.

Companion reply keys aren't auto-registered, so add the Roles key to your admin menu:

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

## Declaring permissions in your app or package

```php
// a service provider's boot()
permissionRegistry()->register('shop.panels.manage', fn () => __('permissions.panels'), 'shop');

// every handler of that feature: reply key, callback queries, state answers, forms
protected ?string $permission = 'shop.panels.manage';
```

Without this package installed, a named permission is for the owner and the developer only.
