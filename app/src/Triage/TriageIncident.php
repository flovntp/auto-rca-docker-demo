<?php

namespace App\Triage;

use App\AutoRca\Incident;

/**
 * A new server error, waiting to be triaged by the worker.
 */
final readonly class TriageIncident
{
    public function __construct(public Incident $incident)
    {
    }
}
