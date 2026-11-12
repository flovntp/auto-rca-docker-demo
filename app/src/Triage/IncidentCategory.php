<?php

namespace App\Triage;

enum IncidentCategory: string
{
    /** A bug in this repository's code, that a change in the code fixes. */
    case CodeBug = 'code_bug';
    /** The servers, the database, the disk, the configuration of the environment. */
    case Infrastructure = 'infrastructure';
    /** A third-party service down or misbehaving. */
    case ExternalDependency = 'external_dependency';
    /** Not worth anyone's time: a bot, a one-off, an expected failure. */
    case Noise = 'noise';
}
