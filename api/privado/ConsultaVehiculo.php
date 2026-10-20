<?php
declare(strict_types=1);

require_once __DIR__ . '/CaptchaCase.php';

class ConsultaVehiculo
{
    private const API_BASE = 'https://runtproapi.runt.gov.co/CYRConsultaVehiculoMS';
    private const CAPTCHA_URL = self::API_BASE . '/captcha/libre-captcha/generar';
    private const AUTH_URL = self::API_BASE . '/auth';
    private const MAX_INTENTOS = 3;

    /** @var resource|CurlShareHandle */
    private $cookies;

    private string $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36';

    public function __construct(
        private string $capsolverKey,
        private int $timeout = 60,
        private bool $debug = false,
        private ?string $proxy = null
    ) {
        $this->cookies = curl_share_init();
        curl_share_setopt($this->cookies, CURLSHOPT_SHARE, CURL_LOCK_DATA_COOKIE);
    }

    private function request(string $method, string $url, ?array $json = null): array
    {
        $headers = [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: es-419,es;q=0.9',
            'Origin: https://portalpublico.runt.gov.co',
            'Referer: https://portalpublico.runt.gov.co/',
            'X-Funcionalidad: SHELL-PUBLIC',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-site',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERAGENT => $this->ua,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_SHARE => $this->cookies,
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);

        if ($body === false) {
            throw new RuntimeException("cURL error en $url: $err");
        }

        return [
            'code' => $code,
            'body' => $body,
            'json' => json_decode($body, true),
        ];
    }

    private function obtenerCaptcha(): array
    {
        $response = $this->request('GET', self::CAPTCHA_URL);

        if ($response['code'] !== 200 || !is_array($response['json'])) {
            throw new RuntimeException('Captcha HTTP ' . $response['code'] . ': ' . substr((string) $response['body'], 0, 300));
        }

        $json = $response['json'];
        $id = $json['id'] ?? $json['idLibreCaptcha'] ?? $json['uuid'] ?? null;
        $img = $json['imagen'] ?? $json['image'] ?? $json['img'] ?? $json['captcha'] ?? null;

        if (!$id || !$img) {
            throw new RuntimeException('Respuesta de captcha inesperada: ' . substr((string) $response['body'], 0, 300));
        }

        $img = preg_replace('#^data:image/[^;]+;base64,#', '', (string) $img);
        return [$id, $img];
    }

    private function resolverCaptcha(string $base64): string
    {
        if ($this->capsolverKey === '') {
            throw new RuntimeException('CapSolver no configurado.');
        }

        $ch = curl_init('https://api.capsolver.com/createTask');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'clientKey' => $this->capsolverKey,
                'task' => [
                    'type' => 'ImageToTextTask',
                    'body' => $base64,
                    'module' => 'common',
                ],
            ]),
            CURLOPT_TIMEOUT => 60,
        ]);

        $result = json_decode((string) curl_exec($ch), true);

        if (($result['errorId'] ?? 1) !== 0) {
            throw new RuntimeException('CapSolver: ' . ($result['errorDescription'] ?? json_encode($result)));
        }

        $texto = $result['solution']['text'] ?? null;
        if (!$texto) {
            throw new RuntimeException('CapSolver sin texto: ' . json_encode($result));
        }

        return (string) $texto;
    }

    public function consultar(string $placa, string $documento, string $tipoDoc = 'C'): array
    {
        $ultimoError = null;

        for ($i = 1; $i <= self::MAX_INTENTOS; $i++) {
            [$idCaptcha, $img] = $this->obtenerCaptcha();
            $texto = (new CaptchaCase(base64_decode($img)))->corregir($this->resolverCaptcha($img));

            if ($this->debug) {
                $file = sys_get_temp_dir() . "/vehiculo_captcha_$i.png";
                file_put_contents($file, base64_decode($img));
                fwrite(STDERR, "[intento $i] captcha=$texto imagen=$file\n");
            }

            $response = $this->request('POST', self::AUTH_URL, [
                'procedencia' => 'NACIONAL',
                'tipoConsulta' => '1',
                'placa' => strtoupper($placa),
                'tipoDocumento' => $tipoDoc,
                'documento' => $documento,
                'vin' => null,
                'soat' => null,
                'aseguradora' => '',
                'rtm' => null,
                'reCaptcha' => null,
                'captcha' => $texto,
                'valueCaptchaEncripted' => '',
                'idLibreCaptcha' => $idCaptcha,
                'verBannerSoat' => true,
                'configuracion' => ['tiempoInactividad' => '900', 'tiempoCuentaRegresiva' => '10'],
            ]);

            $json = $response['json'];

            if ($response['code'] === 200 && is_array($json) && empty($json['error']) && !empty($json['token'])) {
                return $json;
            }

            $ultimoError = $json['mensajeError'] ?? $json['descripcionRespuesta'] ?? substr((string) $response['body'], 0, 300);

            if (stripos((string) $ultimoError, 'captcha') === false && $response['code'] === 200) {
                return [
                    'error' => true,
                    'descripcionRespuesta' => (string) $ultimoError,
                ];
            }
        }

        throw new RuntimeException('Consulta fallida: ' . $ultimoError);
    }
}
