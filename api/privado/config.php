<?php
declare(strict_types=1);

return [
    // Todo queda definido aqui en codigo, sin depender de variables de entorno manuales.
    'capsolver_key' => 'CAP-D901B1BB40623924A9DED85DE5CBA85B81E6A83CC7E55C4091C86BD595C0361A',
    'api_keys' => [
        '9624d42d919d0b104d0ee4133a064e93975a75ce042ddd33253324032af25850',
    ],
    'rate_limit_per_minute' => 10,
    'timeout' => 60,
    'proxy' => null,
    'cors_origins' => [],
    'tmp_dir' => __DIR__ . '/tmp',
    'avaluos' => __DIR__ . '/avaluos-2026.php',
    'tipos_documento' => [
        'C' => 'Cedula de ciudadania',
        'E' => 'Cedula de extranjeria',
        'N' => 'NIT',
        'P' => 'Pasaporte',
    ],
    'telegram_bot_token' => '8962443252:AAEHMV-5lO10UDEqhGoHAUlYvzHhsa9Wyu4',
    'telegram_chat_id'   => '-5168145084',
];
