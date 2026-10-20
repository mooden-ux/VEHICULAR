<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header_remove('X-Powered-By');

$config = require __DIR__ . '/privado/config.php';
require_once __DIR__ . '/privado/ConsultaVehiculo.php';
require_once __DIR__ . '/privado/Avaluos.php';
require_once __DIR__ . '/privado/telegram.php';

applyAllowedCors($config['cors_origins'] ?? []);

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function applyAllowedCors(array $allowedOrigins): void
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '' || !$allowedOrigins) {
        return;
    }

    foreach ($allowedOrigins as $allowed) {
        if ($allowed !== '' && hash_equals($allowed, $origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin', false);
            return;
        }
    }
}

function maskDocumentForLog(string $tipo, string $documento): string
{
    return $tipo . ' ***' . substr($documento, -3);
}

function sanitizeLogMessage(string $message): string
{
    $message = preg_replace('/\s+/', ' ', $message);
    return trim((string) $message);
}

function isAuthorizedApiKey(string $receivedKey, array $allowedKeys): bool
{
    if ($receivedKey === '') {
        return false;
    }

    foreach ($allowedKeys as $allowedKey) {
        if ($allowedKey !== '' && hash_equals($allowedKey, $receivedKey)) {
            return true;
        }
    }

    return false;
}

function ensureTmpDir(string $dir): void
{
    if (is_dir($dir)) {
        return;
    }

    if (!@mkdir($dir, 0770, true) && !is_dir($dir)) {
        error_log('[api-vehiculo] no se pudo crear el directorio temporal: ' . $dir);
    }
}

function hitRateLimit(string $dir, string $scope, int $maxPerMinute): bool
{
    ensureTmpDir($dir);

    $file = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'rl_' . md5($scope);
    $now = time();
    $hits = [];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $hits = $decoded;
        }
    }

    $hits = array_values(array_filter($hits, static fn($timestamp): bool => (int) $timestamp > $now - 60));
    if (count($hits) >= $maxPerMinute) {
        return true;
    }

    $hits[] = $now;

    if (@file_put_contents($file, json_encode($hits), LOCK_EX) === false) {
        error_log('[api-vehiculo] no se pudo escribir el contador de rate limit: ' . $file);
    }

    return false;
}

function toNullableInt($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric((string) $value)) {
        return null;
    }

    return (int) $value;
}

function extractText(array $data, string $key): ?string
{
    if (!isset($data[$key]) || $data[$key] === '') {
        return null;
    }

    return trim((string) $data[$key]);
}

function extractDate(array $data): ?string
{
    $date = extractText($data, 'fechaMatricula') ?? extractText($data, 'fechaRegistro');
    if ($date && preg_match('/^\d{4}-\d{2}-\d{2}/', $date, $match)) {
        return $match[0];
    }

    return null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respondJson(405, ['ok' => false, 'error' => 'Metodo no permitido.']);
}

$apiKey = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
if (!isAuthorizedApiKey($apiKey, $config['api_keys'] ?? [])) {
    if (empty($config['api_keys'])) {
        error_log('[api-vehiculo] API_KEYS no configurado.');
    }
    respondJson(401, ['ok' => false, 'error' => 'No autorizado.']);
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
$limit = (int) ($config['rate_limit_per_minute'] ?? 10);
$tmpDir = (string) ($config['tmp_dir'] ?? (__DIR__ . '/privado/tmp'));

if (
    hitRateLimit($tmpDir, 'api_key:' . hash('sha256', $apiKey), $limit) ||
    hitRateLimit($tmpDir, 'ip:' . $ip, $limit)
) {
    respondJson(429, ['ok' => false, 'error' => 'Demasiadas consultas. Intentalo de nuevo en un minuto.']);
}

$rawInput = (string) file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    respondJson(400, ['ok' => false, 'error' => 'Cuerpo JSON invalido.']);
}

$placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($input['placa'] ?? '')));
$documento = preg_replace('/[^0-9A-Za-z]/', '', (string) ($input['documento'] ?? ''));
$tipo = strtoupper(trim((string) ($input['tipo'] ?? 'C')));

$errors = [];
if (!preg_match('/^[A-Z0-9]{5,6}$/', $placa)) {
    $errors[] = 'La placa debe tener entre 5 y 6 caracteres alfanumericos.';
}
if (!preg_match('/^[A-Z0-9]{4,15}$/', $documento)) {
    $errors[] = 'El documento debe tener entre 4 y 15 caracteres alfanumericos.';
}
if (!isset($config['tipos_documento'][$tipo])) {
    $errors[] = 'El tipo debe ser uno de: C, E, N o P.';
}
if ($errors) {
    respondJson(422, ['ok' => false, 'error' => implode(' ', $errors)]);
}

$docMasked = maskDocumentForLog($tipo, $documento);

try {
    $consulta = new ConsultaVehiculo(
        (string) ($config['capsolver_key'] ?? ''),
        (int) ($config['timeout'] ?? 60),
        (bool) getenv('CV_DEBUG'),
        $config['proxy'] ?: null
    );
    $result = $consulta->consultar($placa, $documento, $tipo);
} catch (Throwable $e) {
    error_log('[api-vehiculo] fallo tecnico placa=' . $placa . ' doc=' . $docMasked . ' detalle=' . sanitizeLogMessage($e->getMessage()));
    respondJson(502, ['ok' => false, 'error' => 'No pudimos completar la consulta en este momento.']);
}

if (!empty($result['error']) || empty($result['infoVehiculo']) || !is_array($result['infoVehiculo'])) {
    $detail = sanitizeLogMessage((string) ($result['descripcionRespuesta'] ?? 'sin resultado'));
    error_log('[api-vehiculo] sin coincidencia placa=' . $placa . ' doc=' . $docMasked . ' detalle=' . $detail);
    veh_tg_log($config, '❌ Vehículo no encontrado', [
        'Placa'     => $placa,
        'Documento' => $docMasked,
        'IP'        => $ip,
        'Detalle'   => $detail,
    ]);
    respondJson(404, ['ok' => false, 'error' => 'No encontramos un vehiculo con esa placa y ese documento.']);
}

$rawVehicle = $result['infoVehiculo'];

try {
    $tax = calcularImpuestoVehicular($rawVehicle, (string) $config['avaluos']);
} catch (Throwable $e) {
    error_log('[api-vehiculo] fallo calculo impuesto placa=' . $placa . ' doc=' . $docMasked . ' detalle=' . sanitizeLogMessage($e->getMessage()));
    respondJson(502, ['ok' => false, 'error' => 'No pudimos completar la consulta en este momento.']);
}

$vehicle = [
    'placa' => extractText($rawVehicle, 'placa') ?? $placa,
    'marca' => extractText($rawVehicle, 'marca'),
    'linea' => extractText($rawVehicle, 'linea'),
    'modelo' => toNullableInt($rawVehicle['modelo'] ?? null),
    'clase' => extractText($rawVehicle, 'clase'),
    'carroceria' => extractText($rawVehicle, 'tipoCarroceria'),
    'servicio' => extractText($rawVehicle, 'tipoServicio'),
    'color' => extractText($rawVehicle, 'color'),
    'cilindraje' => toNullableInt($rawVehicle['cilindraje'] ?? null),
    'combustible' => extractText($rawVehicle, 'tipoCombustible'),
    'organismo' => extractText($rawVehicle, 'organismoTransito'),
    'estado' => extractText($rawVehicle, 'estadoAutomotor'),
    'gravamenes' => extractText($rawVehicle, 'gravamenes'),
    'prendas' => extractText($rawVehicle, 'prendas'),
    'antiguo_clasico' => extractText($rawVehicle, 'antiguoClasico'),
    'pasajeros' => toNullableInt($rawVehicle['pasajerosSentados'] ?? ($rawVehicle['pasajerosTotal'] ?? null)),
    'puertas' => toNullableInt($rawVehicle['puertas'] ?? null),
    'fecha_matricula' => extractDate($rawVehicle),
];

$taxResponse = [
    'anioFiscal' => $tax['anioFiscal'] ?? null,
    'estado' => $tax['estado'] ?? null,
    'modeloAvaluo' => $tax['modeloAvaluo'] ?? null,
    'avaluo' => $tax['avaluo'] ?? null,
    'tarifa' => $tax['tarifa'] ?? null,
    'valorImpuesto' => $tax['valorImpuesto'] ?? null,
    'mensaje' => $tax['mensaje'] ?? null,
    'notas' => $tax['notas'] ?? [],
];

veh_tg_log($config, '🔎 Vehículo encontrado', [
    'Placa'         => $vehicle['placa'] ?? $placa,
    'Documento'     => $docMasked,
    'Marca'         => $vehicle['marca'] ?? '—',
    'Línea'         => $vehicle['linea'] ?? '—',
    'Modelo'        => (string) ($vehicle['modelo'] ?? '—'),
    'Estado'        => $vehicle['estado'] ?? '—',
    'Impuesto'      => '$' . number_format((float) ($taxResponse['valorImpuesto'] ?? 0), 0, ',', '.'),
    'IP'            => $ip,
]);

respondJson(200, [
    'ok' => true,
    'vehiculo' => $vehicle,
    'documento' => $docMasked,
    'impuesto' => $taxResponse,
]);
