<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source\Ipb;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class IpbAnuarioClient
{
    public const ENDPOINT = 'https://www.icalvinus.app/consulta_ipb/anuario.php';

    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? new NoPrivateNetworkHttpClient(HttpClient::create([
            'timeout' => 30.0,
            'verify_peer' => true,
            'verify_host' => true,
        ]));
    }

    public function download(): string
    {
        $response = $this->client->request('POST', self::ENDPOINT, [
            'body' => [
                'buscar' => 'anu_igrejas',
                'tipo' => '1',
            ],
            'headers' => [
                'User-Agent' => 'PrivacyEvidence/0.x research source adapter',
            ],
        ]);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(
                sprintf('IPB Anuário source returned HTTP %d.', $response->getStatusCode()),
            );
        }

        return $response->getContent();
    }
}
