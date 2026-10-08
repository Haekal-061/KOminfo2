<?php

namespace App\Services;

class OpenWAClient
{
    public function sendText(string $recipient, string $text, ?string $idempotencyKey = null): array
    {
        $baseUrl = rtrim((string) env('OPENWA_BASE_URL'), '/');
        $apiKey = (string) env('OPENWA_API_KEY');
        $sessionId = (string) env('OPENWA_SESSION_ID');
        if ($baseUrl === '' || $apiKey === '') {
            throw new \RuntimeException('OPENWA_BASE_URL dan OPENWA_API_KEY wajib dikonfigurasi.');
        }

        $path = (string) env('OPENWA_SEND_PATH') ?: '/api/messages/sendText';
        if (str_contains($path, '{sessionId}') && $sessionId === '') {
            throw new \RuntimeException('OPENWA_SESSION_ID wajib diatur ketika OPENWA_SEND_PATH memakai {sessionId}.');
        }
        $path = str_replace('{sessionId}', rawurlencode($sessionId), $path);
        $client = service('curlrequest', [
            'baseURI' => $baseUrl,
            'timeout' => (float) (env('OPENWA_TIMEOUT') ?: 10),
            'http_errors' => false,
        ]);
        $headers = [
            'X-API-Key' => $apiKey,
            'Accept' => 'application/json',
        ];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        $response = $client->post($path, [
            'headers' => $headers,
            'json' => [
                'to' => str_contains($recipient, '@') ? $recipient : $recipient . '@c.us',
                'content' => $text,
            ],
        ]);
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('OpenWA mengembalikan HTTP ' . $status . ': ' . mb_substr($body, 0, 500));
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : ['response' => $body];
    }
}
