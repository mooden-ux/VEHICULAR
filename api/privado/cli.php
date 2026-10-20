<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc < 3) {
    fwrite(STDERR, "Uso: php api/privado/cli.php <PLACA> <DOCUMENTO> [TIPO: C|E|N|P]\n");
    exit(2);
}

require_once __DIR__ . '/ConsultaVehiculo.php';

$config = require __DIR__ . '/config.php';

try {
    $consulta = new ConsultaVehiculo(
        (string) $config['capsolver_key'],
        (int) $config['timeout'],
        (bool) getenv('CV_DEBUG'),
        $config['proxy'] ?: null
    );
    $resultado = $consulta->consultar((string) $argv[1], (string) $argv[2], (string) ($argv[3] ?? 'C'));
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
