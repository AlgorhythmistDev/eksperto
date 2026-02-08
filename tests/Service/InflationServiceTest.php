<?php

namespace App\Tests\Service;

use App\Service\EvdsService;
use App\Service\InflationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class InflationServiceTest extends TestCase
{
    public function testCalculateInflationThrowsExceptionWhenApiFails(): void
    {
        $evdsService = $this->createMock(EvdsService::class);
        $evdsService->method('fetchInflationData')->willReturn(null);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(function ($key, $callback) {
            $item = $this->createMock(ItemInterface::class);
            return $callback($item);
        });

        $service = new InflationService($evdsService, $cache);

        $startDate = new \DateTime('2024-01-01');
        $endDate = new \DateTime('2024-02-01');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to retrieve inflation data from EVDS API.');

        $service->calculateInflation($startDate, $endDate);
    }

    public function testCalculateInflationUsesApiData(): void
    {
        $evdsService = $this->createMock(EvdsService::class);
        $evdsService->method('fetchInflationData')->willReturn([
            ['Tarih' => '2024-01', 'TP_FG_J0' => '10.00'],
            ['Tarih' => '2024-02', 'TP_FG_J0' => '5.00'],
        ]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(function ($key, $callback) {
            $item = $this->createMock(ItemInterface::class);
            return $callback($item);
        });

        $service = new InflationService($evdsService, $cache);

        $startDate = new \DateTime('2024-01-01');
        $endDate = new \DateTime('2024-03-01');

        $result = $service->calculateInflation($startDate, $endDate);

        $this->assertTrue($result['success']);
        $this->assertEquals('EVDS API', $result['source']);
        $this->assertEquals('15,50', $result['totalInflation']);
    }
}
