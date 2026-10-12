# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/); versioning is
[Semantic Versioning](https://semver.org/), with the 0.x caveat that a minor
bump may carry breaking changes until the API stabilizes at 1.0.

## [Unreleased]

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
