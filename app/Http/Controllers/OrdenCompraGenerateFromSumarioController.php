<?php

namespace App\Http\Controllers;

use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
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

            if ($orders !== []) {
                return redirect(OrdenCompraResource::getUrl('edit', ['record' => $orders[0]]))
                    ->with('status', 'Se genero la ODC para el proveedor seleccionado. Completa los datos del formulario.');
            }

            return redirect()->back()->with('status', 'No habia ODC nuevas por crear para ese proveedor.');
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->back()->with('error', $exception->getMessage());
        }
    }
}
