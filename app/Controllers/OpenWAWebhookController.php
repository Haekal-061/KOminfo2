<?php

namespace App\Controllers;

use App\Services\WhatsAppWebhookService;

class OpenWAWebhookController extends BaseController
{
    public function receive()
    {
        $secret = (string) env('OPENWA_WEBHOOK_SECRET');
        $provided = (string) ($this->request->getHeaderLine('X-Webhook-Secret')
            ?: preg_replace('/^Bearer\s+/i', '', $this->request->getHeaderLine('Authorization')));
        if ($secret === '' || $provided === '' || ! hash_equals($secret, $provided)) {
            log_message('warning', 'Rejected OpenWA webhook authentication from {ip}', ['ip' => $this->request->getIPAddress()]);
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }
        if (! service('throttler')->check('openwa-webhook-' . sha1($this->request->getIPAddress()), 60, 60)) {
            return $this->response->setStatusCode(429)->setJSON(['error' => 'Too many requests']);
        }
        $body = $this->request->getBody();
        if (strlen($body) > 2 * 1024 * 1024) {
            return $this->response->setStatusCode(413)->setJSON(['error' => 'Payload too large']);
        }
        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid JSON payload']);
        }
        try {
            $result = (new WhatsAppWebhookService())->enqueue(
                $payload,
                $this->request->getHeaderLine('Idempotency-Key') ?: null,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            log_message('error', 'Unable to persist OpenWA webhook event: {error}', ['error' => $exception->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Unable to accept webhook event']);
        }
        return $this->response->setStatusCode(202)->setJSON($result);
    }
}
