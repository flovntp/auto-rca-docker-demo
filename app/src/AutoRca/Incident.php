<?php

namespace App\AutoRca;

use Symfony\Component\HttpFoundation\Request;

/**
 * What the agent gets to know about a server error: the exception, where it
 * was thrown, and the request's method and path (never its query string,
 * headers or body, which can hold tokens or personal data).
 */
final readonly class Incident
{
    private function __construct(
        public string $signature,
        public string $class,
        public string $message,
        public string $file,
        public int $line,
        public string $trace,
        public string $request,
        public string $revision,
        public string $occurredAt,
    ) {
    }

    public static function fromThrowable(\Throwable $e, Request $request, string $projectDir, string $revision): self
    {
        $relative = static fn (string $text): string => str_replace($projectDir.'/', '', $text);
        $file = $relative($e->getFile());

        return new self(
            signature: self::signature($e, $file),
            class: $e::class,
            message: mb_substr($e->getMessage(), 0, 500),
            file: $file,
            line: $e->getLine(),
            trace: mb_substr($relative($e->getTraceAsString()), 0, 4000),
            request: $request->getMethod().' '.$request->getPathInfo(),
            revision: $revision,
            occurredAt: (new \DateTimeImmutable())->format(\DATE_ATOM),
        );
    }

    /**
     * The same bug gives the same signature, whatever the ids, amounts or
     * names in its message: numbers and quoted values are left out.
     */
    private static function signature(\Throwable $e, string $file): string
    {
        $message = preg_replace(['/"[^"]*"|\'[^\']*\'/', '/\d+/'], ['"?"', 'N'], $e->getMessage());

        return substr(hash('sha256', implode('|', [$e::class, $file, $e->getLine(), $message])), 0, 12);
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
