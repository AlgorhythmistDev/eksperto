<?php

namespace App\Service;

use DateTime;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class InflationService
{
    private const REFERENCE_PRICE = 100;
    private const CACHE_KEY = 'inflation_data_monthly_v7';
    private const CACHE_TTL = 1; // 24 hours

    public function __construct(
        private readonly EvdsService $evdsService,
        private readonly CacheInterface $cache,
    ) {}

    /**
     * Get inflation data (Cache -> API)
     * Throws RuntimeException if data cannot be fetched.
     */
    private function getInflationData(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);

            // Try fetching from API
            // Date range: 2024-01-01 to today
            // We fetch a wide range to ensure we have all needed data
            // Adjust start date as needed.
            $startDate = '01-01-2024';
            $endDate = "31-01-2026";

            $apiData = $this->evdsService->fetchInflationData($startDate, $endDate);

            if (!$apiData) {
                // If API fails, we must throw an exception as per requirements
                throw new RuntimeException('Failed to retrieve inflation data from EVDS API.');
            }

            $formattedData = [];
            /*
                * EVDS uses different key names usually.
                * Typically: 'Tarih' => '2024-01', 'TP_FG_J0' => '64.77'
                * We need to map this to 'YYYY-MM' => rate
                */
            foreach ($apiData as $record) {
                if (isset($record['Tarih']) && isset($record['TP_FG_J0'])) {
                    // Ensure format is YYYY-MM
                    // EVDS returns 'YYYY-MM' mostly.
                    $date = $record['Tarih'];
                    $rate = (float) $record['TP_FG_J0'];

                    // Handle potential empty or null values
                    if ($rate !== 0.0 || $record['TP_FG_J0'] === '0') {
                        $formattedData[$date] = $rate;
                    }
                }
            }

            if (empty($formattedData)) {
                throw new RuntimeException('EVDS API returned empty data.');
            }

            return $formattedData;
        });
    }

    /**
     * Calculate inflation between two dates using TUIK data
     * 
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return array Key information and calculation details
     * @throws RuntimeException If data cannot be fetched
     */
    public function calculateInflation(DateTime $startDate, DateTime $endDate): array
    {
        // Ensure dates are in correct order
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $data = $this->getInflationData();

        // If the exact month doesn't exist, use the next available
        $monthlyRates = [];
        $currentDate = clone $startDate;

        // Collect all monthly rates in the period
        while ($currentDate < $endDate) {
            $monthStr = $currentDate->format('Y-m');
            // Check existence in our data
            if (isset($data[$monthStr])) {
                $monthlyRates[] = [
                    'month' => $monthStr,
                    'rate' => $data[$monthStr],
                ];
            }
            $currentDate->modify('first day of next month');
        }

        // Calculate compound inflation
        $compoundRate = 1.0;
        foreach ($monthlyRates as $item) {
            $compoundRate *= (1 + $item['rate'] / 100);
        }
        $totalInflation = ($compoundRate - 1) * 100;

        // Calculate prices
        $finalPrice = self::REFERENCE_PRICE * $compoundRate;
        $priceIncrease = $finalPrice - self::REFERENCE_PRICE;

        return [
            'success' => true,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'monthlyRates' => $monthlyRates,
            'totalInflation' => number_format($totalInflation, 2, ',', '.'),
            'totalInflationValue' => $totalInflation,
            'months' => count($monthlyRates),
            'priceIncrease' => number_format($priceIncrease, 2, ',', '.'),
            'priceIncreaseValue' => $priceIncrease,
            'basePriceExample' => number_format(self::REFERENCE_PRICE, 2, ',', '.'),
            'finalPriceExample' => number_format($finalPrice, 2, ',', '.'),
            'source' => 'EVDS API',
        ];
    }

    /**
     * Get available date range for TUIK data
     */
    public function getAvailableDateRange(): array
    {
        try {
            $data = $this->getInflationData();

            // Sort keys to find min and max dates
            $dates = array_keys($data);
            if (empty($dates)) {
                return ['startDate' => null, 'endDate' => null];
            }
            sort($dates);

            return [
                'startDate' => reset($dates),
                'endDate' => end($dates),
            ];
        } catch (\Exception $e) {
            // Return empty range if API fails, caller should handle or it will show empty
            return ['startDate' => null, 'endDate' => null];
        }
    }

    /**
     * Get all available months
     */
    public function getAvailableMonths(): array
    {
        try {
            $data = $this->getInflationData();
            return array_keys($data);
        } catch (\Exception $e) {
            return [];
        }
    }
}
