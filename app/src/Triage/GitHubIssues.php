<?php

namespace App\Triage;

use App\AutoRca\Incident;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The worker's access to GitHub: issues only. Its token can't push code or
 * open pull requests; that one stays with the runner.
 */
final readonly class GitHubIssues
{
    private const LABEL = 'auto-rca';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'GITHUB_ISSUES_TOKEN')] private string $token,
        #[Autowire(env: 'GITHUB_REPO')] private string $repo,
    ) {
    }

    /**
     * @return array{number: int, html_url: string}|null
     */
    public function findOpen(string $signature): ?array
    {
        foreach ($this->request('GET', '/issues', ['query' => ['labels' => self::LABEL, 'state' => 'open', 'per_page' => 100]]) as $issue) {
            if (str_contains($issue['title'], '['.$signature.']')) {
                return $issue;
            }
        }

        return null;
    }

    /**
     * @return array{number: int, html_url: string}
     */
    public function open(Incident $incident, TriageVerdict $verdict, string $usage): array
    {
        $this->request('POST', '/labels', ['json' => ['name' => self::LABEL, 'color' => 'd93f0b', 'description' => 'Opened by the Auto-RCA pipeline']], ignore422: true);

        $next = $verdict->startCodingAgent
            ? 'A coding agent has been started: its pull request will close this issue.'
            : '**No coding agent started**: a human decides what to do.';
        $fence = static fn (string $text): string => "```\n".str_replace('```', "'''", $text)."\n```";

        return $this->request('POST', '/issues', ['json' => [
            'title' => mb_substr(\sprintf('[%s] %s', $incident->signature, $verdict->summary), 0, 200),
            'labels' => [self::LABEL, 'triage:'.$verdict->category->value],
            'body' => implode("\n", [
                '## Triage',
                '',
                '| | |',
                '|---|---|',
                \sprintf('| Category | `%s` |', $verdict->category->value),
                \sprintf('| Confidence | %s |', $verdict->confidence->value),
                \sprintf('| Coding agent | %s |', $verdict->startCodingAgent ? 'started' : 'not started'),
                '',
                '**Suspected cause.** '.$verdict->suspectedCause,
                '',
                '**Evidence.** '.$verdict->evidence,
                '',
                '**Recommendation.** '.$verdict->recommendation,
                '',
                $next,
                '',
                '## Incident',
                '',
                \sprintf('`%s` at `%s:%d`, on `%s`, revision `%s`, first seen %s.', $incident->class, $incident->file, $incident->line, $incident->request, $incident->revision ?: 'unknown', $incident->occurredAt),
                '',
                $fence($incident->message),
                '<details><summary>Stack trace</summary>',
                '',
                $fence($incident->trace),
                '</details>',
                '',
                '---',
                '_Triage: '.$usage.'._',
            ]),
        ]]);
    }

    public function comment(int $number, string $body): void
    {
        $this->request('POST', '/issues/'.$number.'/comments', ['json' => ['body' => $body]]);
    }

    private function request(string $method, string $path, array $options = [], bool $ignore422 = false): array
    {
        $response = $this->httpClient->request($method, 'https://api.github.com/repos/'.$this->repo.$path, $options + [
            'auth_bearer' => $this->token,
            'headers' => ['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'auto-rca-triage'],
        ]);
        if ($ignore422 && 422 === $response->getStatusCode()) {
            return [];
        }

        return $response->toArray();
    }
}
