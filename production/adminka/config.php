<?php
/**
 * Adminka configuration.
 * Generate a new password hash with:  php -r "echo password_hash('yourpass', PASSWORD_DEFAULT);"
 */
return [
    // Login credentials (single admin user, no DB needed)
    'admin_user'    => 'admin',
    // Default password is "changeme" — REPLACE THIS HASH before deploying!
    'admin_hash'    => '$2y$10$ARG5er5BR2XWXGL0NX45w.L3Pi1b1vPnTDj.oU3NpIs.YuGW3Kp32',

    // Directory containing the editable HTML files (site root)
    'site_root'     => __DIR__,

    // Where timestamped backups go (kept outside web root if possible)
    'backup_dir'    => __DIR__ . '/backups',

    // Runtime data: passkey credentials, OAuth state files.
    // Keep outside the web root if possible; otherwise the bundled .htaccess denies access.
    'data_dir'      => __DIR__ . '/data',

    // How many backups to keep per file
    'backup_keep'   => 10,

    // Allowed file extensions for editing
    'extensions'    => ['html', 'htm'],

    // Tags/attributes allowed inside data-editable-type="html" regions
    'allowed_tags'  => ['p','br','b','strong','i','em','u','s','a','ul','ol','li',
                        'h1','h2','h3','h4','h5','h6','blockquote','span'],
    'allowed_attrs' => ['a' => ['href','title','target'], 'span' => ['class']],

    /* ------------------------------------------------------------ sign-in */

    // OAuth / OpenID Connect providers. Only enabled providers show on the
    // login screen. Redirect URI to register with the provider:
    //   https://your-site.example/admin.php?action=oauth_cb&provider=google  (or apple)
    'oauth' => [
        'google' => [
            'enabled'       => false,
            'client_id'     => '',
            'client_secret' => '',
        ],
        'apple' => [
            'enabled'   => false,
            'client_id' => '',                    // Services ID, e.g. com.example.adminka
            'team_id'   => '',                    // 10-char Apple Developer Team ID
            'key_id'    => '',                    // Key ID of the .p8 Sign in with Apple key
            'key_file'  => __DIR__ . '/AuthKey.p8',
        ],
    ],

    // Emails allowed to sign in through OAuth (case-insensitive).
    'oauth_allowed_emails' => ['ykosinets@gmail.com'],

    // Passkey (WebAuthn) credential store. Passkeys are added from the
    // page-list screen after signing in; needs HTTPS (or localhost).
    'passkeys_file' => __DIR__ . '/data/passkeys.json',

    /* -------------------------------------------------------------- media */

    // Media library folders (relative to site_root) used by the image/video
    // pickers in edit mode. Uploads are validated against ext + MIME + size.
    // SVG is deliberately not allowed (can carry scripts).
    'media' => [
        'image' => [
            'dir'       => 'assets/content/image',
            'ext'       => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'],
            'max_bytes' => 10 * 1024 * 1024,
        ],
        'video' => [
            'dir'       => 'assets/content/video',
            'ext'       => ['mp4', 'webm', 'mov'],
            'max_bytes' => 200 * 1024 * 1024,
        ],
    ],
];
