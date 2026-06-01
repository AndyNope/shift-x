<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

date_default_timezone_set(TZ);

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_response(['error' => 'Ungültiges JSON.'], 400);
    }

    return $decoded;
}

function pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = app_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['db_host'],
        $cfg['db_port'],
        $cfg['db_name']
    );

    try {
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        json_response(['error' => 'Datenbankverbindung fehlgeschlagen.'], 500);
    }

    return $pdo;
}

function ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $https,
            'use_strict_mode' => true,
        ]);
    }
}

function current_user(): ?array
{
    ensure_session();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = pdo()->prepare('SELECT id, username, email, employee_number, swp_skill FROM users WHERE id = :id');
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_auth(): array
{
    $user = current_user();
    if ($user === null) {
        json_response(['error' => 'Bitte zuerst anmelden.'], 401);
    }

    return $user;
}

function swp_rank(string $skill): int
{
    return match ($skill) {
        'NONE' => 0,
        'FOERDERBAND' => 1,
        'TREPPE' => 2,
        'HT' => 3,
        default => -1,
    };
}

function can_take_shift(string $provider, string $requiredSkill, string $userSkill): bool
{
    if ($provider === 'DNATA') {
        return swp_rank($userSkill) >= swp_rank('FOERDERBAND');
    }

    if ($provider !== 'SWP') {
        return false;
    }

    return swp_rank($userSkill) >= swp_rank($requiredSkill);
}

function can_modify_shift_before_deadline(string $shiftDate): bool
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $shiftDate . ' 00:00:00');
    if (!$date) {
        return false;
    }

    $deadline = $date->modify('-1 day')->setTime(8, 0, 0);
    $now = new DateTimeImmutable('now');

    return $now <= $deadline;
}

function send_handover_email(array $fromUser, array $toUser, array $shift): bool
{
    $cfg = app_config();
    $subject = sprintf(
        'Schichtabgabe an %s / %s',
        $toUser['username'],
        $toUser['employee_number']
    );

    $body = "Hallo zusammen,\n\n"
        . sprintf(
            "gerne möchte ich die Schicht von %s-%s Uhr an %s / %s abgeben.\n\n",
            $shift['start_time'],
            $shift['end_time'],
            $toUser['username'],
            $toUser['employee_number']
        )
        . sprintf("Meine M-Nummer lautet: %s\n\n", $fromUser['employee_number'])
        . sprintf("Liebe Grüsse\n%s\n", $fromUser['username']);

    $headers = [
        'From: ' . $cfg['mail_from'],
        'Cc: ' . $toUser['email'],
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($cfg['mail_to'], $subject, $body, implode("\r\n", $headers));
}

function normalize_time(string $value): ?string
{
    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        return null;
    }

    [$h, $m] = array_map('intval', explode(':', $value));
    if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
        return null;
    }

    return sprintf('%02d:%02d', $h, $m);
}

function parse_day_list_text(string $input): array
{
    $results = [];
    $lines = preg_split('/\R+/', $input) ?: [];

    foreach ($lines as $line) {
        $clean = trim(preg_replace('/\s+/', ' ', $line));
        if ($clean === '') {
            continue;
        }

        if (!preg_match('/^(\d{4}-\d{2}-\d{2}).*?(\d{4})\s+(\d{4})/', $clean, $m)) {
            continue;
        }

        $date = $m[1];
        $start = substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2);
        $end = substr($m[3], 0, 2) . ':' . substr($m[3], 2, 2);

        $upper = mb_strtoupper($clean);
        $provider = str_contains($upper, 'DNATA') ? 'DNATA' : 'SWP';
        $required = 'FOERDERBAND';
        if (str_contains($upper, 'HT')) {
            $required = 'HT';
        } elseif (str_contains($upper, 'TREPPE')) {
            $required = 'TREPPE';
        }

        $key = $date . $start . $end . $provider . $required;
        $results[$key] = [
            'shift_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'provider' => $provider,
            'required_skill' => $required,
        ];
    }

    return array_values($results);
}
