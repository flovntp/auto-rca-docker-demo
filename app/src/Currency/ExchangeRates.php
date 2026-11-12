<?php

namespace App\Currency;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Today's exchange rates, from the rates provider (RATES_API_URL).
 */
final readonly class ExchangeRates
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'RATES_API_URL')] private string $apiUrl,
    ) {
    }

    public function rate(string $from, string $to): float
    {
        $rates = $this->httpClient->request('GET', rtrim($this->apiUrl, '/').'/latest', [
            'query' => ['from' => $from, 'to' => $to],
            'timeout' => 3,
        ])->toArray();

        return (float) $rates['rates'][$to];
    }
}
