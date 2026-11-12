<?php

namespace App\Command;

use App\Payment\Database;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:db:init', 'Creates the SQLite database of the payments, if needed')]
final class InitDatabaseCommand
{
    public function __construct(private readonly Database $database)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $this->database->createSchema();
        $io->success('Database ready.');

        return 0;
    }
}
