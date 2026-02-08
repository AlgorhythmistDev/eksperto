<?php

namespace App\Tests\Service;

use App\Service\EvdsService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class EvdsServiceTest extends TestCase
{
    public function testFetchInflationDataSendsCorrectRequest(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['items' => []]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('https://evds2.tcmb.gov.tr/service/evds/series=TP.FG.J0'), // Verify URL structure (no ?)
                $this->callback(function ($options) {
                    // Verify header contains key
                    return isset($options['headers']['key']) && $options['headers']['key'] === 'test_key';
                })
            )
            ->willReturn($response);

        $service = new EvdsService($httpClient, 'test_key');
        $service->fetchInflationData('01-01-2024', '01-01-2024');
    }
}
