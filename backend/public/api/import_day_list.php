<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Methode nicht erlaubt.'], 405);
}

$data = read_json_input();
$text = (string) ($data['text'] ?? '');
if (trim($text) === '') {
    json_response(['error' => 'Bitte Day-List Text einfügen.'], 422);
}

json_response(['shifts' => parse_day_list_text($text)]);
