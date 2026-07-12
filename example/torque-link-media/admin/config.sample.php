<?php

return [
    // Generate a hash with:
    // php -r 'echo password_hash("your-password-here", PASSWORD_DEFAULT), PHP_EOL;'
    'password_hash' => 'REPLACE_WITH_PASSWORD_HASH',
    'content_file' => __DIR__ . '/../content/site.json',
    'settings_file' => __DIR__ . '/../content/settings.json',
];
