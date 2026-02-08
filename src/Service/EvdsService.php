<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class EvdsService
{
    private const API_URL = 'https://evds2.tcmb.gov.tr/service/evds/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
    ) {}

    /**
     * Fetch Monthly Inflation Data (TP.FG.J0)
     * 
     * @param string $startDate Format: dd-mm-yyyy (e.g. 01-01-2024)
     * @param string $endDate Format: dd-mm-yyyy
     * @return array|null Returns raw data array or null on failure
     */
    public function fetchInflationData(string $startDate, string $endDate): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            // Series: TP.FG.J0 (Tüketici Fiyat Endeksi - Aylık % Değişim)
            // Frequency: 5 (Monthly)
            // Aggregation Type: avg (Average - though for monthly data it's single point)
            // Formulas: 0 (Level)

            $response = $this->httpClient->request('GET', self::API_URL . "series=TP.FG.J0&startDate={$startDate}&endDate={$endDate}&type=json&frequency=5", [
                'headers' => [
                    'key' => $this->apiKey,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $content = $response->toArray();

            // EVDS wraps items in 'items' key.
            return $content['items'] ?? null;
        } catch (TransportExceptionInterface | \Exception $e) {
            // Log error here if logger is available
            return null;
        }
    }
}
