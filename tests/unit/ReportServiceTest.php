<?php

namespace Tests\Unit;

use App\Repositories\DatabaseRepository;
use App\Services\ReportService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ReportServiceTest extends CIUnitTestCase
{
    private BaseConnection $testDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDb = db_connect('tests', false);
        $this->testDb->query('CREATE TABLE db_tickets (id INTEGER PRIMARY KEY, ticket_number TEXT, reporter_id INTEGER, category_id INTEGER, service_type_id INTEGER, priority_id INTEGER, status_id INTEGER, assignee_user_id INTEGER, team_id INTEGER, subject TEXT, description TEXT, created_at TEXT, deleted_at TEXT)');
        $this->testDb->query('CREATE TABLE db_employees (id INTEGER PRIMARY KEY, employee_number TEXT, name TEXT, whatsapp_number TEXT)');
        $this->testDb->query('CREATE TABLE db_categories (id INTEGER PRIMARY KEY, name TEXT)');
        $this->testDb->query('CREATE TABLE db_service_types (id INTEGER PRIMARY KEY, name TEXT)');
        $this->testDb->query('CREATE TABLE db_priorities (id INTEGER PRIMARY KEY, name TEXT)');
        $this->testDb->query('CREATE TABLE db_ticket_statuses (id INTEGER PRIMARY KEY, name TEXT)');
        $this->testDb->query('CREATE TABLE db_users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->testDb->query('CREATE TABLE db_teams (id INTEGER PRIMARY KEY, name TEXT)');

        $this->testDb->table('employees')->insert(['id' => 1, 'employee_number' => 'EMP-1', 'name' => 'Reporter One', 'whatsapp_number' => '628123456789']);
        $this->testDb->table('categories')->insertBatch([
            ['id' => 1, 'name' => 'Hardware'],
            ['id' => 2, 'name' => 'Network'],
        ]);
        $this->testDb->table('service_types')->insert(['id' => 1, 'name' => 'Support']);
        $this->testDb->table('priorities')->insert(['id' => 1, 'name' => 'High']);
        $this->testDb->table('ticket_statuses')->insert(['id' => 1, 'name' => 'New']);
        $this->testDb->table('users')->insert(['id' => 1, 'name' => 'Operator One']);
        $this->testDb->table('teams')->insert(['id' => 1, 'name' => 'Support Team']);
        $this->testDb->table('tickets')->insertBatch([
            ['id' => 1, 'ticket_number' => 'TCK-20261008-0001', 'reporter_id' => 1, 'category_id' => 1, 'service_type_id' => 1, 'priority_id' => 1, 'status_id' => 1, 'assignee_user_id' => 1, 'team_id' => 1, 'subject' => 'Laptop issue', 'description' => 'First report', 'created_at' => '2026-10-08 10:00:00', 'deleted_at' => null],
            ['id' => 2, 'ticket_number' => 'TCK-20261008-0002', 'reporter_id' => 1, 'category_id' => 2, 'service_type_id' => 1, 'priority_id' => 1, 'status_id' => 1, 'assignee_user_id' => 1, 'team_id' => 1, 'subject' => 'Network issue', 'description' => 'Second report', 'created_at' => '2026-10-08 11:00:00', 'deleted_at' => null],
        ]);
    }

    protected function tearDown(): void
    {
        $this->testDb->close();
        parent::tearDown();
    }

    public function testListReturnsFilteredAndSearchableReportRows(): void
    {
        $service = new ReportService(new DatabaseRepository($this->testDb));

        $result = $service->list(['category_id' => '1'], 10, 0, 'Laptop');

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['filtered']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('TCK-20261008-0001', $result['rows'][0]['ticket_number']);
        $this->assertSame('Hardware', $result['rows'][0]['category']);

        $csv = $service->exportCsv(['category_id' => '1', 'keyword' => 'Laptop']);
        $this->assertStringContainsString('TCK-20261008-0001', $csv);
        $this->assertStringNotContainsString('TCK-20261008-0002', $csv);
    }

    public function testCsvExportRejectsNonStringSearch(): void
    {
        $service = new ReportService(new DatabaseRepository($this->testDb));

        $this->expectException(\InvalidArgumentException::class);
        $service->exportCsv(['keyword' => ['invalid']]);
    }

    public function testListRejectsMalformedFilterValues(): void
    {
        $service = new ReportService(new DatabaseRepository($this->testDb));

        $this->expectException(\InvalidArgumentException::class);
        $service->list(['category_id' => ['1']], 10, 0);
    }
}
