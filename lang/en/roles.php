<?php

return [
    'reply_key' => 'Roles 🛡',
    'denied' => 'You can’t do that: it would hand out access you don’t have.',

    'index' => [
        'text' => [
            'list' => "🛡 Roles\n\nEach role is a set of permissions. Give roles to users to control what they can open.",
            'empty' => "🛡 Roles\n\nNo roles yet. Create one, tick its permissions, then give it to users.",
        ],
        'keys' => [
            'role' => ':name · :permissions permissions · :members members',
            'create' => '➕ New role',
        ],
    ],

    'show' => [
        'text' => "🛡 :name\n\nPermissions:\n:permissions\n\nMembers: :members",
        'none' => '(none)',
        'keys' => [
            'permissions' => '🔐 Permissions',
            'members' => '👥 Members',
            'rename' => '✏️ Rename',
            'delete' => '🗑 Delete',
        ],
    ],

    'permissions' => [
        'text' => "🔐 Permissions of “:name”\n\nTap one to turn it on or off.",
        'wildcard' => '⭐ Everything',
    ],

    'members' => [
        'text' => [
            'list' => '👥 Members of “:name”. Tap one to remove them.',
            'empty' => '👥 “:name” has no members yet.',
            'enter' => 'Send the user’s numeric Telegram ID or their @username. They must have started the bot.',
            'notFound' => 'No user of this bot matches that. Send a numeric Telegram ID or an @username.',
            'added' => ':user now has this role.',
        ],
        'keys' => [
            'add' => '➕ Add member',
        ],
        'lock' => 'Waiting for the user to add',
    ],

    'create' => [
        'enter' => 'Send a name for the new role (up to 64 characters).',
        'lock' => 'Waiting for the role name',
        'invalid' => 'The name must be 1 to 64 characters.',
        'taken' => 'There is already a role called “:name”.',
        'done' => 'Role “:name” created. Now tick its permissions.',
    ],

    'rename' => [
        'enter' => 'Send the new name for “:name”.',
        'lock' => 'Waiting for the new name',
        'done' => 'Renamed to “:name”.',
    ],

    'delete' => [
        'text' => 'Delete the role “:name”? Its members lose the access it gave them.',
        'keys' => [
            'confirm' => '🗑 Yes, delete',
        ],
        'done' => 'Role deleted.',
    ],

    'user' => [
        'text' => "🛡 Roles of :name\n\nTap a role to give or take it.",
        'section' => '🛡 Roles: :roles',
        'noRoles' => 'none',
    ],
];
