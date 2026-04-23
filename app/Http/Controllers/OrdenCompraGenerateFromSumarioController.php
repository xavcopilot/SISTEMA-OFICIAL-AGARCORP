<?php

namespace App\Http\Controllers;

use App\Models\Sumario;
use App\Support\SumarioFinanceApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrdenCompraGenerateFromSumarioController
{
    public function __invoke(Request $request, Sumario $sumario): RedirectResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()?->can('GenerateOdcs:Sumario'), 403);

        $providerId = (int) ($request->input('provider_id') ?? 0);
        $providerName = trim((string) ($request->input('provider_name') ?? ''));

        if ($providerId <= 0 && $providerName === '') {
            return redirect()->back()->with('error', 'Proveedor invalido para generar ODC.');
        }

        try {
            $orders = app(SumarioFinanceApprovalService::class)
                ->generateOrdersForProvider(
                    $sumario,
                    auth()->user(),
                    $providerId > 0 ? $providerId : null,
                    $providerName !== '' ? $providerName : null
                );

            return redirect()->back()->with('status', 'Se generaron ' . count($orders) . ' ODC para el proveedor seleccionado.');
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->back()->with('error', $exception->getMessage());
        }
    }
}
