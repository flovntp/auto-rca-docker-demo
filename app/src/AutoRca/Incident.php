<?php

namespace App\AutoRca;

use Symfony\Component\HttpFoundation\Request;

/**
 * What the agent gets to know about a server error: the exception, where it
 * was thrown, and where it came from: the request's method and path (never its
 * query string, headers or body, which can hold tokens or personal data), or
 * the class of the message the worker failed to handle (never its content).
 */
final readonly class Incident
{
    public const SOURCE_APP = 'app';
    public const SOURCE_WORKER = 'worker';

    private function __construct(
        public string $signature,
        public string $class,
        public string $message,
        public string $file,
        public int $line,
        public string $trace,
        public string $source,
        public string $origin,
        public string $revision,
        public string $occurredAt,
    ) {
    }

    /** A 500 in the web app. */
    public static function fromRequest(\Throwable $e, Request $request, string $projectDir, string $revision): self
    {
        return self::create($e, self::SOURCE_APP, $request->getMethod().' '.$request->getPathInfo(), $projectDir, $revision);
    }

    /** A message the worker gave up on, after its retries. */
    public static function fromMessage(\Throwable $e, object $message, string $projectDir, string $revision): self
    {
        return self::create($e, self::SOURCE_WORKER, 'message '.$message::class, $projectDir, $revision);
    }

    private static function create(\Throwable $e, string $source, string $origin, string $projectDir, string $revision): self
    {
        $relative = static fn (string $text): string => str_replace($projectDir.'/', '', $text);
        $file = $relative($e->getFile());
        $message = $relative($e->getMessage());

        return new self(
            signature: self::signature($e, $file, $message),
            class: $e::class,
            message: mb_substr($message, 0, 500),
            file: $file,
            line: $e->getLine(),
            trace: mb_substr($relative($e->getTraceAsString()), 0, 4000),
            source: $source,
            origin: $origin,
            revision: $revision,
            occurredAt: (new \DateTimeImmutable())->format(\DATE_ATOM),
        );
    }

    /**
     * The same bug gives the same signature, whatever the ids, amounts or
     * names in its message: numbers and quoted values are left out.
     */
    private static function signature(\Throwable $e, string $file, string $message): string
    {
        $message = preg_replace(['/"[^"]*"|\'[^\']*\'/', '/\d+/'], ['"?"', 'N'], $message);

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
