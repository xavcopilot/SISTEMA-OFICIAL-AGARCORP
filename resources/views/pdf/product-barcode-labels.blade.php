<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiquetas de Productos</title>
    <style>
        @page {
            margin: 10mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
            margin: 0;
        }

        .sheet {
            width: 100%;
            border-collapse: collapse;
        }

        .cell {
            width: 33.33%;
            vertical-align: top;
            padding: 4mm 3mm;
        }

        .label {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 3mm;
            text-align: center;
            min-height: 34mm;
        }

        .sku {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.03em;
            margin-bottom: 2mm;
        }

        .barcode svg {
            width: 100%;
            height: 14mm;
        }

        .desc {
            margin-top: 2mm;
            font-size: 9px;
            line-height: 1.25;
            min-height: 9mm;
        }
    </style>
</head>
<body>
    <table class="sheet">
        @foreach (array_chunk($labels, 3) as $row)
            <tr>
                @foreach ($row as $item)
                    <td class="cell">
                        <div class="label">
                            <div class="sku">{{ $item['sku'] }}</div>
                            <div class="barcode">{!! $item['barcode_svg'] !!}</div>
                            <div class="desc">{{ $item['descripcion'] }}</div>
                        </div>
                    </td>
                @endforeach

                @for ($i = count($row); $i < 3; $i++)
                    <td class="cell"></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
