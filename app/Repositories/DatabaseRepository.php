<?php

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;

class DatabaseRepository
{
    private BaseConnection $connection;

    public function __construct(BaseConnection|self|null $connection = null)
    {
        $this->connection = $connection instanceof self
            ? $connection->connection()
            : ($connection ?? db_connect());
    }

    public function table(string $table)
    {
        return $this->connection->table($table);
    }

    public function query(string $sql, array $binds = [])
    {
        return $this->connection->query($sql, $binds);
    }

    public function insertID(): int
    {
        return (int) $this->connection->insertID();
    }

    public function affectedRows(): int
    {
        return $this->connection->affectedRows();
    }

    public function transBegin(): bool
    {
        return $this->connection->transBegin();
    }

    public function transCommit(): bool
    {
        return $this->connection->transCommit();
    }

    public function transRollback(): bool
    {
        return $this->connection->transRollback();
    }

    public function transStatus(): bool
    {
        return $this->connection->transStatus();
    }

    public function connection(): BaseConnection
    {
        return $this->connection;
    }
}
