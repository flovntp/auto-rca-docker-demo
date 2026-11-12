<?php

namespace App\Triage;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * What the triage agent answers, as a JSON schema it must follow (Symfony AI
 * structured output): no free text to parse.
 */
final class TriageVerdict
{
    #[Schema(description: 'code_bug: a change in this repository fixes it. infrastructure: servers, database, disk, environment configuration. external_dependency: a third-party service. noise: not worth anyone\'s time.')]
    public IncidentCategory $category;

    #[Schema(description: 'One sentence for the issue title: what failed and why, for a developer.')]
    public string $summary;

    #[Schema(description: 'The most likely root cause, with the file and line when it is in the code.')]
    public string $suspectedCause;

    #[Schema(description: 'What supports it: the lines of code read, the parts of the stack trace or message.')]
    public string $evidence;

    #[Schema(description: 'What a human should do next, in one or two sentences.')]
    public string $recommendation;

    public Confidence $confidence;

    #[Schema(description: 'True only when a change in this repository is the fix for the root cause, and a coding agent should write it. False for an outage, a configuration or data problem, or noise, even if better error handling would help.')]
    public bool $startCodingAgent;
}
