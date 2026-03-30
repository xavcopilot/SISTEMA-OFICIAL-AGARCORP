<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud de Compra #{{ $solicitudCompra->id }}</title>
    <style>
        @page {
            margin: 20px 24px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.35;
        }

        h1 {
            margin: 0 0 4px;
            font-size: 18px;
            text-transform: uppercase;
        }

        .muted {
            color: #6b7280;
        }

        .section {
            margin-top: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }

        .meta .label {
            display: block;
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 2px;
        }

        .items th,
        .items td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }

        .items th {
            background: #f3f4f6;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.3px;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .signatures td {
            border: 1px solid #d1d5db;
            padding: 10px 8px;
            width: 25%;
            vertical-align: bottom;
        }

        .signatures .line {
            height: 32px;
            border-bottom: 1px solid #111827;
            margin-bottom: 6px;
        }

        .small {
            font-size: 9px;
        }
    </style>
</head>
<body>
    <h1>Solicitud de Compra</h1>
    <div class="muted">Documento #{{ $solicitudCompra->id }} | Estado: {{ $solicitudCompra->estado ?? 'Pendiente' }}</div>

    <div class="section">
        <table class="meta">
            <tr>
                <td>
                    <span class="label">Codigo de Control</span>
                    {{ $solicitudCompra->codigo_control ?: '-' }}
                </td>
                <td>
                    <span class="label">Codigo Control Procura</span>
                    {{ $solicitudCompra->codigo_control_procura ?: '-' }}
                </td>
                <td>
                    <span class="label">Fecha Solicitud</span>
                    {{ optional($solicitudCompra->fecha_solicitud)->format('d/m/Y') ?: '-' }}
                </td>
                <td>
                    <span class="label">Prioridad</span>
                    {{ $solicitudCompra->prioridad ?: '-' }}
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Departamento Solicitante</span>
                    {{ $solicitudCompra->departamento_solicitante ?: '-' }}
                </td>
                <td>
                    <span class="label">Tipo de Solicitud</span>
                    {{ $solicitudCompra->tipo_solicitud ?: '-' }}
                </td>
                <td>
                    <span class="label">Centro</span>
                    {{ $solicitudCompra->centro ?: '-' }}
                </td>
                <td>
                    <span class="label">Cuenta</span>
                    {{ $solicitudCompra->cuenta ?: '-' }}
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Elemento</span>
                    {{ $solicitudCompra->elemento ?: '-' }}
                </td>
                <td colspan="3">
                    <span class="label">Contrato</span>
                    {{ $solicitudCompra->contrato ?: '-' }}
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <span class="label">Para ser usado en</span>
                    {{ $solicitudCompra->para_ser_usado_en ?: '-' }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 7%">Item</th>
                    <th style="width: 43%">Descripcion</th>
                    <th style="width: 15%">Unidad</th>
                    <th style="width: 12%" class="text-right">Solicitada</th>
                    <th style="width: 12%" class="text-right">Existencia</th>
                    <th style="width: 11%" class="text-right">A comprar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($solicitudCompra->items as $item)
                    <tr>
                        <td>{{ $item->item ?: $loop->iteration }}</td>
                        <td>{{ $item->descripcion ?: '-' }}</td>
                        <td>{{ $item->unidad_medida ?: '-' }}</td>
                        <td class="text-right">{{ $item->cantidad_solicitada ?: '-' }}</td>
                        <td class="text-right">{{ $item->cantidad_existencia ?: '-' }}</td>
                        <td class="text-right">{{ $item->cantidad_a_comprar ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">No hay items cargados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <table class="meta">
            <tr>
                <td>
                    <span class="label">Solicitado por</span>
                    {{ $solicitudCompra->solicitadoPor?->name ?: '-' }}
                    <div class="small muted">Cargo: {{ $solicitudCompra->cargo_solicitante ?: '-' }}</div>
                    <div class="small muted">Fecha: {{ optional($solicitudCompra->fecha_solicitante)->format('d/m/Y') ?: '-' }}</div>
                </td>
                <td>
                    <span class="label">Por almacen</span>
                    {{ $solicitudCompra->porAlmacen?->name ?: '-' }}
                    <div class="small muted">Cargo: {{ $solicitudCompra->cargo_almacen ?: '-' }}</div>
                    <div class="small muted">Fecha: {{ optional($solicitudCompra->fecha_almacen)->format('d/m/Y') ?: '-' }}</div>
                </td>
                <td>
                    <span class="label">Aprobado por</span>
                    {{ $solicitudCompra->aprobadoPor?->name ?: '-' }}
                    <div class="small muted">Cargo: {{ $solicitudCompra->cargo_aprobador ?: '-' }}</div>
                    <div class="small muted">Fecha: {{ optional($solicitudCompra->fecha_aprobador)->format('d/m/Y') ?: '-' }}</div>
                </td>
                <td>
                    <span class="label">Recibido por</span>
                    {{ $solicitudCompra->recibidoPor?->name ?: '-' }}
                    <div class="small muted">Cargo: {{ $solicitudCompra->cargo_receptor ?: '-' }}</div>
                    <div class="small muted">Fecha: {{ optional($solicitudCompra->fecha_receptor)->format('d/m/Y') ?: '-' }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
