<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Methode nicht erlaubt.'], 405);
}

$data = read_json_input();
$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');

$stmt = pdo()->prepare('SELECT id, username, email, password_hash, employee_number, swp_skill FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response(['error' => 'Login fehlgeschlagen.'], 401);
}

ensure_session();
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];

unset($user['password_hash']);
json_response(['ok' => true, 'user' => $user]);
