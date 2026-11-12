<?php

namespace App\AutoRca;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Asks the runner, the only service allowed to start containers, to start an
 * agent for an incident. The body is signed with a secret shared with the
 * runner, so nothing else on the network can make it start one.
 */
final readonly class RunnerClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'RCA_RUNNER_URL')] private string $runnerUrl,
        #[Autowire(env: 'RCA_RUNNER_SECRET')] private string $secret,
    ) {
    }

    public function start(Incident $incident): void
    {
        $body = json_encode($incident->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        try {
            $response = $this->httpClient->request('POST', rtrim($this->runnerUrl, '/').'/runs', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Rca-Signature' => 'sha256='.hash_hmac('sha256', $body, $this->secret),
                ],
                'body' => $body,
                'timeout' => 2,
            ]);
            $this->logger->info('Auto-RCA run requested for incident {signature}: {status}', [
                'signature' => $incident->signature,
                'status' => $response->getStatusCode(),
            ]);
        } catch (\Throwable $e) {
            // The page has already failed: the agent must never make it worse.
            $this->logger->warning('Auto-RCA runner unreachable: {error}', ['error' => $e->getMessage()]);
        }
    }
}
