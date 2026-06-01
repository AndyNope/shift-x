<?php

declare(strict_types=1);

function cfg(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

const TZ = 'Europe/Zurich';

function app_config(): array
{
    return [
        'db_host' => cfg('DB_HOST', '127.0.0.1'),
        'db_port' => (int) cfg('DB_PORT', '3306'),
        'db_name' => cfg('DB_NAME', 'shiftx'),
        'db_user' => cfg('DB_USER', 'root'),
        'db_pass' => cfg('DB_PASS', ''),
        'mail_to' => cfg('MAIL_TO', 'private@andynope.com'),
        'mail_from' => cfg('MAIL_FROM', 'noreply@shiftx.local'),
    ];
}
