<?php

namespace Tests\Unit;

use App\Repositories\DatabaseRepository;
use App\Services\WhatsAppWebhookService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class WhatsAppWebhookServiceTest extends CIUnitTestCase
{
    private BaseConnection $testDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDb = db_connect('tests', false);
        $this->testDb->query(
            'CREATE TABLE db_webhook_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel TEXT NOT NULL,
                external_message_id TEXT,
                event_type TEXT,
                payload TEXT NOT NULL,
                processing_status TEXT NOT NULL DEFAULT "received",
                received_at TEXT NOT NULL,
                processed_at TEXT,
                attempt_count INTEGER NOT NULL DEFAULT 0,
                available_at TEXT,
                locked_at TEXT,
                last_error TEXT,
                UNIQUE (channel, event_type, external_message_id)
            )',
        );
        $this->testDb->query(
            'CREATE TABLE db_ticket_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel TEXT,
                external_message_id TEXT
            )',
        );
    }

    protected function tearDown(): void
    {
        $this->testDb->close();
        parent::tearDown();
    }

    public function testOpenWaV5EventsAreQueuedAndDeduplicated(): void
    {
        $service = new WhatsAppWebhookService(new DatabaseRepository($this->testDb));
        $payload = $this->v5Payload('message-1');

        $first = $service->enqueue($payload);
        $second = $service->enqueue($payload);

        $this->assertSame(['accepted' => true, 'duplicate' => false], $first);
        $this->assertSame(['accepted' => true, 'duplicate' => true], $second);
        $this->assertSame(1, $this->testDb->table('webhook_events')->countAllResults());
        $this->assertSame('received', $this->testDb->table('webhook_events')->get()->getRowArray()['processing_status']);
    }

    public function testWorkerIgnoresGatewayEchoesAndGroupMessages(): void
    {
        $service = new WhatsAppWebhookService(new DatabaseRepository($this->testDb));
        $service->enqueue($this->v5Payload('self-message', true));
        $groupPayload = $this->v5Payload('group-message');
        $groupPayload['payload']['message']['from'] = '120363000000@g.us';
        $groupPayload['payload']['message']['isGroupMsg'] = true;
        $service->enqueue($groupPayload);

        $result = $service->processPending(10);

        $this->assertSame(['processed' => 2, 'completed' => 2, 'failed' => 0], $result);
        $statuses = $this->testDb->table('webhook_events')->select('processing_status')->orderBy('id')->get()->getResultArray();
        $this->assertSame(['ignored', 'ignored'], array_column($statuses, 'processing_status'));
    }

    public function testLegacyDataPayloadRemainsAccepted(): void
    {
        $service = new WhatsAppWebhookService(new DatabaseRepository($this->testDb));
        $result = $service->enqueue([
            'event' => 'message.received',
            'data' => ['id' => 'legacy-message', 'from' => '628123456789@c.us', 'body' => 'ADUAN'],
        ]);

        $event = $this->testDb->table('webhook_events')->get()->getRowArray();
        $this->assertFalse($result['duplicate']);
        $this->assertSame('legacy-message', $event['external_message_id']);
    }

    public function testEnvelopeIdempotencyKeyTakesPrecedenceOverMessageId(): void
    {
        $service = new WhatsAppWebhookService(new DatabaseRepository($this->testDb));
        $first = $this->v5Payload('message-1');
        $first['idempotencyKey'] = 'durable-delivery-1';
        $retry = $this->v5Payload('message-2');
        $retry['idempotencyKey'] = 'durable-delivery-1';

        $service->enqueue($first);
        $result = $service->enqueue($retry);

        $this->assertTrue($result['duplicate']);
        $this->assertSame(1, $this->testDb->table('webhook_events')->countAllResults());
    }

    public function testInvalidV5EnvelopeIsRejectedBeforeQueueing(): void
    {
        $service = new WhatsAppWebhookService(new DatabaseRepository($this->testDb));

        $this->expectException(\InvalidArgumentException::class);
        $service->enqueue([
            'webhookId' => 'webhook-instance',
            'sessionId' => 'session-kominfo',
            'event' => 'message.received',
            'payload' => ['message' => ['id' => 'message-1']],
        ]);
    }

    public function testFailedBusinessProcessingIsScheduledForRetry(): void
    {
        $service = new WhatsAppWebhookService(new DatabaseRepository($this->testDb));
        $service->enqueue($this->v5Payload('retry-message'));

        $result = $service->processPending(10);
        $event = $this->testDb->table('webhook_events')->get()->getRowArray();

        $this->assertSame(['processed' => 1, 'completed' => 0, 'failed' => 1], $result);
        $this->assertSame('received', $event['processing_status']);
        $this->assertSame(1, (int) $event['attempt_count']);
        $this->assertNotNull($event['last_error']);
        $this->assertGreaterThan(time(), strtotime($event['available_at']));
    }

    private function v5Payload(string $messageId, bool $fromMe = false): array
    {
        return [
            'webhookId' => 'webhook-instance',
            'sessionId' => 'session-kominfo',
            'event' => 'message.received',
            'timestamp' => 1730000000000,
            'payload' => [
                'message' => [
                    'id' => $messageId,
                    'from' => '628123456789@c.us',
                    'body' => 'ADUAN',
                    'fromMe' => $fromMe,
                    'isGroupMsg' => false,
                ],
            ],
        ];
    }
}
