<?php

namespace App\Console\Commands;

use App\Support\BcvRateService;
use Illuminate\Console\Command;

class UpdateBcvRate extends Command
{
    protected $signature = 'bcv:update
        {--force : Reemplaza la tasa ya guardada del dia}
        {--rate= : Valor manual de tasa para guardar hoy (ej: 49.123456)}';

    protected $description = 'Actualiza la tasa BCV del dia desde la fuente configurada.';

    public function handle(BcvRateService $bcvRateService): int
    {
        try {
            $manualRate = trim((string) ($this->option('rate') ?? ''));

            if ($manualRate !== '') {
                $normalized = str_replace(',', '.', $manualRate);
                $parsedRate = (float) $normalized;

                if ($parsedRate <= 0) {
                    $this->error('La opcion --rate debe ser un numero mayor a cero.');

                    return self::INVALID;
                }

                $rate = $bcvRateService->setTodayRate($parsedRate);
            } else {
                $rate = $bcvRateService->refreshTodayRate((bool) $this->option('force'));
            }

            $this->info('Tasa BCV actualizada.');
            $this->line('Fecha: ' . (string) optional($rate->rate_date)->format('Y-m-d'));
            $this->line('Tasa: ' . number_format((float) $rate->rate, 6, ',', '.'));
            $this->line('Fuente: ' . (string) $rate->source);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('No se pudo actualizar la tasa BCV: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
