<?php
declare(strict_types=1);

/**
 * Envía un evento con sus datos al chat de Telegram configurado.
 * No lanza errores: el logging nunca debe romper el flujo principal.
 */
function veh_tg_log(array $config, string $title, array $fields = []): void
{
    $token  = (string) ($config['telegram_bot_token'] ?? '');
    $chatId = (string) ($config['telegram_chat_id']   ?? '');
    if ($token === '' || $chatId === '') {
        return;
    }

    $lines = [$title];
    foreach ($fields as $key => $value) {
        if ($value === null || $value === '') {
            $value = '—';
        }
        $lines[] = (string) $key . ': ' . (string) $value;
    }

    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'                  => $chatId,
            'text'                     => implode("\n", $lines),
            'disable_web_page_preview' => true,
        ]),
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function veh_get_ip(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        $val = trim((string) ($_SERVER[$header] ?? ''));
        if ($val === '') continue;
        $ip = trim(explode(',', $val)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }
    // Fallback: acepta cualquier IP incluyendo privadas (entornos locales)
    foreach ($headers as $header) {
        $val = trim((string) ($_SERVER[$header] ?? ''));
        if ($val === '') continue;
        $ip = trim(explode(',', $val)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return 'desconocida';
}

function veh_tg_escape(string $text): string
{
    return str_replace(['*', '_', '`', '['], ['\\*', '\\_', '\\`', '\\['], $text);
}
