<?php

namespace App\AutoRca;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * One triage per bug, whether it fails in the web app or in the worker: the
 * first process to create the incident's directory wins. mkdir() is atomic, so
 * this holds across processes and containers sharing the volume, even when the
 * same 500 comes a hundred times at once.
 */
final readonly class IncidentClaims
{
    public function __construct(
        #[Autowire('%kernel.share_dir%/auto-rca')] private string $claimDir,
    ) {
    }

    public function claim(string $signature): bool
    {
        if (!is_dir($this->claimDir) && !@mkdir($this->claimDir, 0o770, true) && !is_dir($this->claimDir)) {
            return false;
        }

        return @mkdir($this->claimDir.'/'.$signature, 0o770);
    }
}
