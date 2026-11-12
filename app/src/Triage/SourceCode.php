<?php

namespace App\Triage;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The triage agent's only tools: reading the application's own code.
 * Read-only, and limited to src/, config/ and tests/: never .env files,
 * var/ or vendor/.
 */
#[AsTool('list_source_files', 'Lists the PHP and YAML files of the application (src/, config/, tests/), with their paths relative to the project.', method: 'list')]
#[AsTool('read_source', 'Reads lines of a file of the application, with line numbers. Use the path relative to the project, as in the stack trace (e.g. src/Payment/PaymentRecorder.php).', method: 'read')]
final readonly class SourceCode
{
    private const ALLOWED = ['src/', 'config/', 'tests/'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    public function list(): string
    {
        $files = [];
        foreach (self::ALLOWED as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->projectDir.'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (\in_array($file->getExtension(), ['php', 'yaml'], true)) {
                    $files[] = substr($file->getPathname(), \strlen($this->projectDir) + 1);
                }
            }
        }
        sort($files);

        return implode("\n", $files);
    }

    /**
     * @param string $path     the file, relative to the project
     * @param int    $fromLine first line to read (from 1)
     * @param int    $toLine   last line to read, at most 200 lines after the first
     */
    public function read(string $path, int $fromLine = 1, int $toLine = 120): string
    {
        $real = realpath($this->projectDir.'/'.ltrim($path, '/'));
        $relative = false === $real ? null : substr($real, \strlen($this->projectDir) + 1);
        $allowed = null !== $relative && str_starts_with($real, $this->projectDir.'/')
            && array_filter(self::ALLOWED, static fn (string $dir) => str_starts_with($relative, $dir));
        if (!$allowed || !is_file($real)) {
            return \sprintf('Not readable: "%s". Only files under src/, config/ and tests/ can be read.', $path);
        }

        $lines = file($real, \FILE_IGNORE_NEW_LINES);
        $from = max(1, $fromLine);
        $to = min(\count($lines), $toLine, $from + 200);
        $out = [];
        for ($n = $from; $n <= $to; ++$n) {
            $out[] = \sprintf('%4d  %s', $n, $lines[$n - 1]);
        }

        return implode("\n", $out) ?: '(no lines in this range)';
    }
}
