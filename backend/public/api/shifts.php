<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = pdo()->query('SELECT s.id, s.owner_id, s.shift_date, s.start_time, s.end_time, s.provider, s.required_skill, s.status, u.username AS owner_name
                          FROM shifts s
                          JOIN users u ON u.id = s.owner_id
                          WHERE s.status = "OPEN"
                          ORDER BY s.shift_date, s.start_time');
    json_response(['shifts' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Methode nicht erlaubt.'], 405);
}

$data = read_json_input();
$shiftDate = trim((string) ($data['shift_date'] ?? ''));
$startTime = normalize_time(trim((string) ($data['start_time'] ?? '')));
$endTime = normalize_time(trim((string) ($data['end_time'] ?? '')));
$provider = strtoupper(trim((string) ($data['provider'] ?? '')));
$requiredSkill = strtoupper(trim((string) ($data['required_skill'] ?? 'FOERDERBAND')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftDate) || $startTime === null || $endTime === null || !in_array($provider, ['SWP', 'DNATA'], true) || !in_array($requiredSkill, ['NONE', 'FOERDERBAND', 'TREPPE', 'HT'], true)) {
    json_response(['error' => 'Ungültige Schichtdaten.'], 422);
}

if (!can_modify_shift_before_deadline($shiftDate)) {
    json_response(['error' => 'Schichten können nur bis Vortag 08:00 abgegeben werden.'], 409);
}

if ($provider === 'DNATA') {
    $requiredSkill = 'FOERDERBAND';
}

$insert = pdo()->prepare('INSERT INTO shifts (owner_id, shift_date, start_time, end_time, provider, required_skill, status) VALUES (:owner_id, :shift_date, :start_time, :end_time, :provider, :required_skill, "OPEN")');
$insert->execute([
    'owner_id' => (int) $user['id'],
    'shift_date' => $shiftDate,
    'start_time' => $startTime,
    'end_time' => $endTime,
    'provider' => $provider,
    'required_skill' => $requiredSkill,
]);

json_response(['ok' => true]);
