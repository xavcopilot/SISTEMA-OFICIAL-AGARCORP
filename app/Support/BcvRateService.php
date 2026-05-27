<?php

namespace App\Support;

use App\Models\BcvRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class BcvRateService
{
    private const DEFAULT_BCV_URL = 'https://www.bcv.org.ve/estadisticas/tipo-cambio-de-referencia-smc';

    public function rateForOrderCreation(): ?float
    {
        $businessDayRate = $this->businessDayRate();

        if ($businessDayRate) {
            return (float) $businessDayRate->rate;
        }

        $latest = $this->latestRate();

        return $latest ? (float) $latest->rate : null;
    }

    public function todayRate(): ?BcvRate
    {
        return BcvRate::query()
            ->whereDate('rate_date', $this->todayDate())
            ->first();
    }

    public function latestRate(): ?BcvRate
    {
        return BcvRate::query()
            ->orderByDesc('rate_date')
            ->first();
    }

    public function refreshTodayRate(bool $force = false): BcvRate
    {
        $targetDate = $this->businessReferenceDate()->toDateString();

        if (! $force) {
            $existing = BcvRate::query()->whereDate('rate_date', $targetDate)->first();

            if ($existing) {
                return $existing;
            }
        }

        $fetched = $this->fetchRate($targetDate);
        $rateDate = (string) ($fetched['rate_date'] ?? $targetDate);

        return BcvRate::query()->updateOrCreate(
            ['rate_date' => $rateDate],
            [
                'rate' => round((float) $fetched['rate'], 6),
                'source' => (string) $fetched['source'],
                'source_url' => $fetched['source_url'] ? (string) $fetched['source_url'] : null,
                'fetched_at' => now(),
                'payload' => $fetched['payload'] ?? null,
            ]
        );
    }

    public function setTodayRate(float $rate, string $source = 'MANUAL'): BcvRate
    {
        $today = $this->businessReferenceDate()->toDateString();

        return BcvRate::query()->updateOrCreate(
            ['rate_date' => $today],
            [
                'rate' => round(max(0, $rate), 6),
                'source' => $source,
                'source_url' => null,
                'fetched_at' => now(),
                'payload' => [
                    'manual' => true,
                    'captured_at' => now()->toIso8601String(),
                ],
            ]
        );
    }

    public function businessDayRate(): ?BcvRate
    {
        $businessDate = $this->businessReferenceDate()->toDateString();

        return BcvRate::query()
            ->whereDate('rate_date', '<=', $businessDate)
            ->orderByDesc('rate_date')
            ->first();
    }

    private function fetchRate(string $targetDate): array
    {
        $sourceType = (string) config('services.bcv.source', 'bcv_website');

        if ($sourceType === 'dolarapi_ve') {
            return $this->fetchFromDolarApiVe($targetDate);
        }

        if ($sourceType === 'json_api') {
            return $this->fetchFromJsonApi();
        }

        return $this->fetchFromBcvWebsite();
    }

    private function fetchFromBcvWebsite(): array
    {
        $url = (string) config('services.bcv.website_url', self::DEFAULT_BCV_URL);

        try {
            $response = $this->httpClientWithSslPolicy()
                ->accept('text/html')
                ->get($url);
        } catch (\Throwable $exception) {
            if (! $this->hasLocalSslIssue($exception->getMessage())) {
                throw $exception;
            }

            $response = Http::timeout(20)
                ->withOptions(['verify' => false])
                ->accept('text/html')
                ->get($url);
        }

        if (! $response->successful()) {
            throw new \RuntimeException('No se pudo consultar la pagina del BCV para actualizar la tasa.');
        }

        $html = (string) $response->body();
        $rate = $this->parseRateFromBcvHtml($html);

        if ($rate <= 0) {
            throw new \RuntimeException('No se pudo extraer la tasa USD/BS desde la pagina del BCV.');
        }

        return [
            'rate' => $rate,
            'source' => 'BCV_WEB',
            'source_url' => $url,
            'payload' => [
                'parsed' => 'regex',
                'captured_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function fetchFromJsonApi(): array
    {
        $url = (string) config('services.bcv.api_url', '');

        if ($url === '') {
            throw new \RuntimeException('BCV_API_URL no esta configurada para fuente json_api.');
        }

        $request = $this->httpClientWithSslPolicy()->acceptJson();

        $token = (string) config('services.bcv.api_token', '');

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('La API de tasa BCV respondio con error.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('La API de tasa BCV devolvio un payload invalido.');
        }

        $rate = $this->extractRateFromPayload($payload);

        if ($rate <= 0) {
            throw new \RuntimeException('No se encontro un valor de tasa valido en el payload de la API BCV.');
        }

        return [
            'rate' => $rate,
            'source' => 'BCV_API',
            'source_url' => $url,
            'payload' => $payload,
        ];
    }

    private function fetchFromDolarApiVe(string $targetDate): array
    {
        $currentUrl = (string) config('services.bcv.dolarapi_current_url', 'https://ve.dolarapi.com/v1/dolares/oficial');
        $historyUrl = (string) config('services.bcv.dolarapi_history_url', 'https://ve.dolarapi.com/v1/historicos/dolares/oficial');

        $request = $this->httpClientWithSslPolicy()->acceptJson();
        $token = (string) config('services.bcv.api_token', '');

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->get($currentUrl);

        if (! $response->successful()) {
            throw new \RuntimeException('No se pudo consultar DolarApi para tasa oficial.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('DolarApi devolvio un payload invalido para tasa oficial.');
        }

        $referenceDate = CarbonImmutable::parse($targetDate, 'America/Caracas')->startOfDay();
        $currentRate = $this->extractRateFromPayload($payload);
        $currentDate = $this->extractDateFromPayload($payload, ['fechaActualizacion', 'fecha', 'updated_at']);

        if ($currentRate <= 0) {
            throw new \RuntimeException('DolarApi no devolvio una tasa oficial valida.');
        }

        if ($currentDate && $currentDate->startOfDay()->gt($referenceDate)) {
            $historyResponse = $request->get($historyUrl);

            if (! $historyResponse->successful()) {
                throw new \RuntimeException('DolarApi devolvio fecha futura y no se pudo consultar historico oficial.');
            }

            $history = $historyResponse->json();

            if (! is_array($history)) {
                throw new \RuntimeException('DolarApi historico oficial devolvio payload invalido.');
            }

            foreach ($history as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rowDate = $this->extractDateFromPayload($row, ['fecha', 'fechaActualizacion', 'updated_at']);

                if (! $rowDate || $rowDate->startOfDay()->gt($referenceDate)) {
                    continue;
                }

                $rowRate = $this->extractRateFromPayload($row);

                if ($rowRate <= 0) {
                    continue;
                }

                return [
                    'rate' => $rowRate,
                    'rate_date' => $rowDate->toDateString(),
                    'source' => 'DOLARAPI_VE',
                    'source_url' => $historyUrl,
                    'payload' => [
                        'selected' => 'history_not_future',
                        'selected_date' => $rowDate->toDateString(),
                        'current_payload' => $payload,
                        'history_row' => $row,
                    ],
                ];
            }
        }

        return [
            'rate' => $currentRate,
            'rate_date' => $currentDate?->toDateString() ?? $referenceDate->toDateString(),
            'source' => 'DOLARAPI_VE',
            'source_url' => $currentUrl,
            'payload' => [
                'selected' => 'current',
                'selected_date' => $currentDate?->toDateString(),
                'current_payload' => $payload,
            ],
        ];
    }

    private function parseRateFromBcvHtml(string $html): float
    {
        $compact = preg_replace('/\s+/u', ' ', $html) ?? $html;

        $patterns = [
            '/(?:USD|US\\$|D[oó]lar(?:es)?)[^0-9]{0,140}([0-9]{1,3}(?:\\.[0-9]{3})*,[0-9]{2,10})/iu',
            '/(?:Tipo\s+de\s+Cambio)[^0-9]{0,160}([0-9]{1,3}(?:\\.[0-9]{3})*,[0-9]{2,10})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $compact, $matches) !== 1) {
                continue;
            }

            $parsed = $this->toDecimal((string) ($matches[1] ?? '0'));

            if ($parsed > 0) {
                return $parsed;
            }
        }

        return 0.0;
    }

    private function extractRateFromPayload(array $payload): float
    {
        $candidate = Arr::get($payload, 'rate')
            ?? Arr::get($payload, 'tasa')
            ?? Arr::get($payload, 'precio')
            ?? Arr::get($payload, 'promedio')
            ?? Arr::get($payload, 'usd.rate')
            ?? Arr::get($payload, 'data.rate')
            ?? Arr::get($payload, 'data.tasa')
            ?? Arr::get($payload, 'data.precio');

        if ($candidate !== null) {
            return $this->toDecimal((string) $candidate);
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveArrayIterator($payload)) as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $normalizedKey = mb_strtolower((string) $key);
            if (! in_array($normalizedKey, ['rate', 'tasa', 'precio', 'promedio', 'value', 'price'], true)) {
                continue;
            }

            $parsed = $this->toDecimal((string) $value);
            if ($parsed > 0) {
                return $parsed;
            }
        }

        return 0.0;
    }

    private function extractDateFromPayload(array $payload, array $keys): ?CarbonImmutable
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);

            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse((string) $value, 'America/Caracas');
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function toDecimal(string $value): float
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return 0.0;
        }

        $normalized = str_replace(['Bs', 'Bs.', 'VES', ' '], '', $trimmed);

        // Formato es-VE: 1.234,56 -> 1234.56
        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            // Formato API: 1234.56 (dejar punto decimal)
            $normalized = preg_replace('/[^0-9.\-]/', '', $normalized) ?? $normalized;
        }

        return max(0, (float) $normalized);
    }

    private function todayDate(): string
    {
        return CarbonImmutable::now('America/Caracas')->toDateString();
    }

    private function businessReferenceDate(): CarbonImmutable
    {
        $today = CarbonImmutable::now('America/Caracas');
        $dayOfWeek = (int) $today->dayOfWeekIso;

        return match ($dayOfWeek) {
            6 => $today->subDay(),
            7 => $today->subDays(2),
            default => $today,
        };
    }

    private function httpClientWithSslPolicy(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(20)
            ->withOptions(['verify' => $this->shouldVerifySsl()]);
    }

    private function shouldVerifySsl(): bool
    {
        $value = config('services.bcv.verify_ssl', true);

        if (is_bool($value)) {
            return $value;
        }

        return ! in_array(mb_strtolower((string) $value), ['0', 'false', 'off', 'no'], true);
    }

    private function hasLocalSslIssue(string $message): bool
    {
        $normalized = mb_strtolower($message);

        return str_contains($normalized, 'ssl certificate problem')
            || str_contains($normalized, 'unable to get local issuer certificate');
    }
}
