<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Methode nicht erlaubt.'], 405);
}

$data = read_json_input();
$shiftId = (int) ($data['shift_id'] ?? 0);
if ($shiftId <= 0) {
    json_response(['error' => 'Ungültige Schicht-ID.'], 422);
}

$select = pdo()->prepare('SELECT s.id, s.owner_id, s.shift_date, s.start_time, s.end_time, s.provider, s.required_skill, s.status,
                                 u.username AS owner_name, u.email AS owner_email, u.employee_number AS owner_employee_number
                          FROM shifts s
                          JOIN users u ON u.id = s.owner_id
                          WHERE s.id = :id LIMIT 1');
$select->execute(['id' => $shiftId]);
$shift = $select->fetch();

if (!$shift || $shift['status'] !== 'OPEN') {
    json_response(['error' => 'Schicht ist nicht verfügbar.'], 409);
}

if ((int) $shift['owner_id'] === (int) $user['id']) {
    json_response(['error' => 'Eigene Schicht kann nicht übernommen werden.'], 409);
}

if (!can_modify_shift_before_deadline($shift['shift_date'])) {
    json_response(['error' => 'Schichten können nur bis Vortag 08:00 getauscht werden.'], 409);
}

if (!can_take_shift($shift['provider'], $shift['required_skill'], $user['swp_skill'])) {
    json_response(['error' => 'Skill reicht für diese Schicht nicht aus.'], 403);
}

$update = pdo()->prepare('UPDATE shifts SET status = "CLAIMED", claimer_id = :claimer_id, claimed_at = NOW() WHERE id = :id AND status = "OPEN"');
$update->execute(['claimer_id' => (int) $user['id'], 'id' => $shiftId]);

if ($update->rowCount() !== 1) {
    json_response(['error' => 'Schicht konnte nicht übernommen werden.'], 409);
}

$fromUser = [
    'username' => $shift['owner_name'],
    'email' => $shift['owner_email'],
    'employee_number' => $shift['owner_employee_number'],
];
$toUser = [
    'username' => $user['username'],
    'email' => $user['email'],
    'employee_number' => $user['employee_number'],
];

send_handover_email($fromUser, $toUser, $shift);

json_response(['ok' => true]);
