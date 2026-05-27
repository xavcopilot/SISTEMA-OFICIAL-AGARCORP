<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vista previa ODC no disponible</title>
    <style>
        body {
            margin: 0;
            padding: 1rem;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }

        .shell {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            gap: 1rem;
        }

        .alert {
            padding: 1rem 1.1rem;
            border: 1px solid #fbbf24;
            border-radius: 14px;
            background: #fffbeb;
        }

        .alert h1 {
            margin: 0 0 0.4rem;
            font-size: 1.1rem;
        }

        .alert p {
            margin: 0;
            line-height: 1.55;
            color: #78350f;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0.65rem 1rem;
            border-radius: 10px;
            background: #0f766e;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .summary {
            border: 1px solid #dbe3ef;
            border-radius: 16px;
            background: #fff;
            padding: 1rem;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="alert">
            <h1>Vista previa PDF no disponible para la ODC {{ $ordenCompra->correlativo_odc ?? $ordenCompra->id }}</h1>
            <p>{{ $message }}</p>
        </div>

        <div class="actions">
            <a class="btn" href="{{ $excelUrl }}">Descargar Excel de la ODC</a>
            <a class="btn btn-secondary" href="{{ route('ordenes-compra.formato.print', ['ordenCompra' => $ordenCompra, 'variant' => $variant]) }}">Reintentar vista previa</a>
        </div>

        <div class="summary">
            {!! $summaryHtml !!}
        </div>
    </div>
</body>
</html>