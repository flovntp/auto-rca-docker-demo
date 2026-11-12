<?php

namespace App\Payment;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The SQLite database holding the payments, on a Docker volume.
 */
final class Database
{
    private ?\PDO $pdo = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/data/app.db')] private readonly string $path,
    ) {
    }

    public function pdo(): \PDO
    {
        return $this->pdo ??= new \PDO('sqlite:'.$this->path, options: [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    public function createSchema(): void
    {
        if (!is_dir(\dirname($this->path))) {
            mkdir(\dirname($this->path), 0o770, true);
        }
        $this->pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS invoice_balances (invoice_id INTEGER PRIMARY KEY, paid REAL NOT NULL DEFAULT 0);
            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                created_at TEXT NOT NULL
            );
            INSERT OR IGNORE INTO invoice_balances (invoice_id) VALUES (1), (2), (3);
            SQL);
    }
}
