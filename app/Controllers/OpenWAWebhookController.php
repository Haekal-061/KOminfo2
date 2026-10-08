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
        if (! service('throttler')->check('openwa-webhook:' . $this->request->getIPAddress(), 60, 60)) {
            return $this->response->setStatusCode(429)->setJSON(['error' => 'Too many requests']);
        }
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid JSON payload']);
        }
        try {
            $result = (new WhatsAppWebhookService())->handle($payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $exception->getMessage()]);
        }
        return $this->response->setJSON($result);
    }
}
