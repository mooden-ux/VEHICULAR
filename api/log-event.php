<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$config = require __DIR__ . '/privado/config.php';
require_once __DIR__ . '/privado/telegram.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$limpiar = static function ($v, int $max = 300): string {
    $v = is_scalar($v) ? (string) $v : '';
    return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? ''), 0, $max);
};

$event  = $limpiar($data['event'] ?? 'Evento', 120);
$fields = is_array($data['data'] ?? null) ? $data['data'] : [];

$sanitized = [];
foreach ($fields as $key => $value) {
    $sanitized[$limpiar((string) $key, 60)] = $limpiar((string) ($value ?? ''), 200);
}

$sanitized['IP'] = (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');

veh_tg_log($config, $event, $sanitized);

echo json_encode(['ok' => true]);
