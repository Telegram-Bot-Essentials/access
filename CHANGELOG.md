# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/); versioning is
[Semantic Versioning](https://semver.org/), with the 0.x caveat that a minor
bump may carry breaking changes until the API stabilizes at 1.0.

## [Unreleased]

## [0.1.0-beta.2] - 2026-11-01

### Fixed

- Requires user-management `~0.0.27` again, not `^0.0.27`, which on 0.0.x
  allowed only that one patch and blocked every later user-management
  release.

## [0.1.0-beta.1] - 2026-11-01

Requires essence 0.19, which has no access model: handlers name Laravel
Gate abilities. Roles now answer the Gate, and hold abilities. Breaking
(see **Upgrading**).

### Added

- Default roles per bot, from `tbe-access.default_roles` (`main` for the
  main bot, `sub` for the rest; `Admin => ['*']` out of the box). A new bot
  gets them on creation (stancl's `TenantCreated`).
- `tbe:access:sync-roles [--bot=]` creates the default roles existing bots
  lack. It never changes a role that exists.
- `config/tbe-access.php`, publishable with `--tag=tbe-access-config`.

### Changed

- `RoleAuthorizer` is `RoleGate`, registered as a `Gate::before`. It answers
  for `BotUser`s only: owner, developer, or a role granting the ability
  passes; anything else gets `null`, so the app's other gates still decide.
- The roles checklist lists essence's `abilities()` catalog, read from the
  handlers, in place of the removed permission registry.
- `Permissions` is `Abilities`; the `access_roles.permissions` column is
  `abilities` (a migration renames it). `Role::permissionList()` is
  `abilityList()`, `AccessManager::permissionsOf()` is `abilitiesOf()` and
  `togglePermission()` is `toggleAbility()`. The checklist's callback
  method is `abilities`.
- `AccessManager::isSuperuser()` checks the owner and the developer itself.
- The `access.roles.manage` label lives in `lang/<locale>/abilities.php`,
  keyed by the ability name.

### Removed

- `RoleAuthorizer::usersWith()`: essence's `ownerAlert()` goes to the owner
  only.

### Upgrading

1. `php artisan migrate`, then `php artisan tbe:access:sync-roles`.
2. Rename `permissions` to `abilities` wherever you create roles
   (`Role::create([... 'abilities' => [...]])`).
3. Publish the config if you want default roles other than `Admin`.

## [0.0.2] - 2026-10-12

### Changed

- Requires essence 0.18. A flow's state is read and written on the webhook's
  conversation (`wHook()->conversation()`) instead of the user, so it stays
  in the chat it started in.

## [0.0.1] - 2026-10-12

### Added

- Flat, owner-managed roles per bot (`access_roles`, `access_role_user`).
- `RoleAuthorizer`, bound to essence's `AuthorizesAccess`. The owner and the
  developer always pass; everyone else needs a role granting the permission.
  `usersWith()` returns a permission's holders, so `adminAlert()` reaches
  everyone granted `essence.admin_alerts`.
- Roles screens: create, rename and delete roles, a permission checklist and
  member management, plus a Roles button on user-management profiles.
- Delegable management through `access.roles.manage`, bounded by the
  delegate's own permissions.
