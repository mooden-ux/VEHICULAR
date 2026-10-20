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

function veh_tg_escape(string $text): string
{
    return str_replace(['*', '_', '`', '['], ['\\*', '\\_', '\\`', '\\['], $text);
}
