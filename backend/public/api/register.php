<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Methode nicht erlaubt.'], 405);
}

$data = read_json_input();
$username = trim((string) ($data['username'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$employeeNumber = trim((string) ($data['employee_number'] ?? ''));
$skill = strtoupper(trim((string) ($data['swp_skill'] ?? 'NONE')));

$validSkills = ['NONE', 'FOERDERBAND', 'TREPPE', 'HT'];

if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $employeeNumber === '' || !in_array($skill, $validSkills, true)) {
    json_response(['error' => 'Bitte alle Felder korrekt ausfüllen.'], 422);
}

$stmt = pdo()->prepare('SELECT id FROM users WHERE email = :email OR employee_number = :employee_number LIMIT 1');
$stmt->execute(['email' => $email, 'employee_number' => $employeeNumber]);
if ($stmt->fetch()) {
    json_response(['error' => 'E-Mail oder Mitarbeiternummer existiert bereits.'], 409);
}

$insert = pdo()->prepare(
    'INSERT INTO users (username, email, password_hash, employee_number, swp_skill) VALUES (:username, :email, :password_hash, :employee_number, :swp_skill)'
);
$insert->execute([
    'username' => $username,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'employee_number' => $employeeNumber,
    'swp_skill' => $skill,
]);

json_response(['ok' => true]);
