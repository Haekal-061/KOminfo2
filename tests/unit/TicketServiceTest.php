<?php

namespace Tests\Unit;

use App\Repositories\DatabaseRepository;
use App\Services\TicketService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class TicketServiceTest extends CIUnitTestCase
{
    private BaseConnection $testDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDb = db_connect('tests', false);
        $this->testDb->query('CREATE TABLE db_tickets (id INTEGER PRIMARY KEY, ticket_number TEXT, reporter_id INTEGER, channel TEXT, category_id INTEGER, service_type_id INTEGER, priority_id INTEGER, status_id INTEGER, assignee_user_id INTEGER, team_id INTEGER, subject TEXT, description TEXT, resolution TEXT, first_response_at TEXT, resolved_at TEXT, closed_at TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        $this->testDb->query('CREATE TABLE db_employees (id INTEGER PRIMARY KEY, name TEXT, whatsapp_number TEXT)');
        $this->testDb->query('CREATE TABLE db_categories (id INTEGER PRIMARY KEY, name TEXT, is_active INTEGER)');
        $this->testDb->query('CREATE TABLE db_service_types (id INTEGER PRIMARY KEY, category_id INTEGER, name TEXT, is_active INTEGER)');
        $this->testDb->query('CREATE TABLE db_priorities (id INTEGER PRIMARY KEY, name TEXT, color TEXT, is_active INTEGER)');
        $this->testDb->query('CREATE TABLE db_ticket_statuses (id INTEGER PRIMARY KEY, name TEXT, code TEXT, color TEXT)');
        $this->testDb->query('CREATE TABLE db_users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->testDb->query('CREATE TABLE db_teams (id INTEGER PRIMARY KEY, name TEXT)');
        $this->testDb->query('CREATE TABLE db_ticket_activities (id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id INTEGER, user_id INTEGER, activity_type TEXT, description TEXT, metadata TEXT, created_at TEXT)');

        $this->testDb->table('employees')->insert(['id' => 1, 'name' => 'Reporter', 'whatsapp_number' => '628123456789']);
        $this->testDb->table('categories')->insertBatch([
            ['id' => 1, 'name' => 'Category One', 'is_active' => 1],
            ['id' => 2, 'name' => 'Category Two', 'is_active' => 1],
        ]);
        $this->testDb->table('service_types')->insertBatch([
            ['id' => 1, 'category_id' => 1, 'name' => 'Service One', 'is_active' => 1],
            ['id' => 2, 'category_id' => 2, 'name' => 'Service Two', 'is_active' => 1],
        ]);
        $this->testDb->table('priorities')->insert(['id' => 1, 'name' => 'Normal', 'color' => '#123456', 'is_active' => 1]);
        $this->testDb->table('ticket_statuses')->insert(['id' => 1, 'name' => 'New', 'code' => 'NEW', 'color' => '#123456']);
        $this->testDb->table('tickets')->insert([
            'id' => 1, 'ticket_number' => 'TCK-20261008-0001', 'reporter_id' => 1, 'channel' => 'admin',
            'category_id' => 1, 'service_type_id' => 1, 'priority_id' => 1, 'status_id' => 1,
            'subject' => 'Subject', 'description' => 'Description', 'created_at' => '2026-10-08 10:00:00',
            'updated_at' => '2026-10-08 10:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        $this->testDb->close();
        parent::tearDown();
    }

    public function testUpdateRejectsServiceFromDifferentCategory(): void
    {
        $service = new TicketService(new DatabaseRepository($this->testDb));

        try {
            $service->update(1, ['category_id' => 2, 'service_type_id' => 1]);
            $this->fail('A service belonging to another category must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Jenis layanan tidak sesuai dengan kategori atau tidak aktif.', $exception->getMessage());
        }

        $ticket = $this->testDb->table('tickets')->where('id', 1)->get()->getRowArray();
        $this->assertSame('1', (string) $ticket['category_id']);
        $this->assertSame('1', (string) $ticket['service_type_id']);
    }

    public function testUpdateAcceptsServiceFromSelectedCategory(): void
    {
        $service = new TicketService(new DatabaseRepository($this->testDb));

        $updated = $service->update(1, ['category_id' => 2, 'service_type_id' => 2]);

        $this->assertSame('2', (string) $updated['category_id']);
        $this->assertSame('2', (string) $updated['service_type_id']);
        $this->assertSame(1, $this->testDb->table('ticket_activities')->where('ticket_id', 1)->countAllResults());
    }
}
