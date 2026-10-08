<?php

namespace Tests\Unit;

use App\Repositories\DatabaseRepository;
use App\Services\DashboardService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DashboardServiceTest extends CIUnitTestCase
{
    private BaseConnection $testDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDb = db_connect('tests', false);
        $this->testDb->query('CREATE TABLE db_ticket_statuses (id INTEGER PRIMARY KEY, name TEXT, code TEXT, color TEXT, sort_order INTEGER)');
        $this->testDb->query('CREATE TABLE db_categories (id INTEGER PRIMARY KEY, name TEXT, color TEXT)');
        $this->testDb->query('CREATE TABLE db_priorities (id INTEGER PRIMARY KEY, name TEXT, color TEXT, sort_order INTEGER)');
        $this->testDb->query('CREATE TABLE db_tickets (id INTEGER PRIMARY KEY, status_id INTEGER, category_id INTEGER, priority_id INTEGER, created_at TEXT, deleted_at TEXT)');

        $this->testDb->table('ticket_statuses')->insertBatch([
            ['id' => 1, 'name' => 'Baru', 'code' => 'NEW', 'color' => '#123456', 'sort_order' => 1],
            ['id' => 2, 'name' => 'Selesai', 'code' => 'DONE', 'color' => '#654321', 'sort_order' => 2],
        ]);
        $this->testDb->table('categories')->insertBatch([
            ['id' => 1, 'name' => 'Jaringan', 'color' => '#123456'],
            ['id' => 2, 'name' => 'Aplikasi', 'color' => '#654321'],
        ]);
        $this->testDb->table('priorities')->insertBatch([
            ['id' => 1, 'name' => 'Low', 'color' => '#123456', 'sort_order' => 1],
            ['id' => 2, 'name' => 'High', 'color' => '#654321', 'sort_order' => 2],
        ]);
    }

    protected function tearDown(): void
    {
        $this->testDb->close();
        parent::tearDown();
    }

    public function testDataIncludesConfiguredOptionsWithZeroCounts(): void
    {
        $metrics = (new DashboardService(new DatabaseRepository($this->testDb)))->data('2026-10-01', '2026-10-08');

        $this->assertSame(0, $metrics['total']);
        $this->assertSame(['Baru', 'Selesai'], array_column($metrics['statuses'], 'name'));
        $this->assertSame([0, 0], array_map('intval', array_column($metrics['statuses'], 'total')));
        $this->assertSame(['Aplikasi', 'Jaringan'], array_column($metrics['categories'], 'name'));
        $this->assertSame(['Low', 'High'], array_column($metrics['priorities'], 'name'));
    }

    public function testCountsOnlyNonDeletedTicketsWithinSelectedDates(): void
    {
        $this->testDb->table('tickets')->insertBatch([
            ['id' => 1, 'status_id' => 1, 'category_id' => 2, 'priority_id' => 2, 'created_at' => '2026-10-05 12:00:00', 'deleted_at' => null],
            ['id' => 2, 'status_id' => 2, 'category_id' => 1, 'priority_id' => 1, 'created_at' => '2026-09-30 12:00:00', 'deleted_at' => null],
            ['id' => 3, 'status_id' => 2, 'category_id' => 1, 'priority_id' => 1, 'created_at' => '2026-10-06 12:00:00', 'deleted_at' => '2026-10-07 12:00:00'],
        ]);

        $metrics = (new DashboardService(new DatabaseRepository($this->testDb)))->data('2026-10-01', '2026-10-08');

        $this->assertSame(1, $metrics['total']);
        $this->assertSame([1, 0], array_map('intval', array_column($metrics['statuses'], 'total')));
        $this->assertSame([1, 0], array_map('intval', array_column($metrics['categories'], 'total')));
        $this->assertSame([0, 1], array_map('intval', array_column($metrics['priorities'], 'total')));
        $this->assertSame([['day' => '2026-10-05', 'total' => 1]], $metrics['trend']);
    }
}
