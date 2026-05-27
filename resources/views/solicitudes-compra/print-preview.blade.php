<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impresion Solicitud {{ $solicitudCompra->id }}</title>
    <style>
        :root {
            --bg: #f6f8fb;
            --panel: #ffffff;
            --ink: #1f2937;
            --muted: #64748b;
            --accent: #0f766e;
            --accent-dark: #0b5f58;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top left, #ffffff 0%, var(--bg) 60%);
            color: var(--ink);
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            gap: 0.75rem;
            align-items: center;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #dbe3ef;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(4px);
        }

        .title {
            margin-right: auto;
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        .hint {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .btn {
            border: 0;
            border-radius: 10px;
            padding: 0.6rem 0.95rem;
            font-size: 0.92rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--accent-dark);
        }

        .btn-secondary {
            background: #e8eef7;
            color: #0f172a;
        }

        .canvas {
            padding: 0.9rem;
        }

        iframe {
            width: 100%;
            height: calc(100vh - 82px);
            border: 1px solid #dbe3ef;
            border-radius: 12px;
            background: #fff;
        }

        @media print {
            .toolbar {
                display: none !important;
            }

            .canvas {
                padding: 0;
            }

            iframe {
                border: 0;
                border-radius: 0;
                height: 100vh;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="title">N-de Control de la Solicitud: {{ $codigoControlVisible }}</div>
        <div class="hint">Usa este boton para Imprimir o Guardar como PDF</div>
        <button id="printBtn" class="btn btn-primary" type="button">Imprimir / Guardar PDF</button>
    </div>

    <div class="canvas">
        <iframe id="pdfFrame" src="{{ $pdfUrl }}" title="Vista previa PDF"></iframe>
    </div>

    <script>
        (function () {
            const frame = document.getElementById('pdfFrame');
            const printBtn = document.getElementById('printBtn');

            function triggerPrint() {
                const pdfUrl = frame ? frame.getAttribute('src') : null;

                if (pdfUrl) {
                    const popup = window.open(pdfUrl, '_blank', 'noopener,noreferrer');

                    if (popup) {
                        popup.focus();
                        return;
                    }
                }

                if (!frame || !frame.contentWindow) {
                    window.print();
                    return;
                }

                frame.contentWindow.focus();
                frame.contentWindow.print();
            }

            printBtn.addEventListener('click', triggerPrint);

            document.addEventListener('keydown', function (event) {
                const isMac = navigator.platform.toUpperCase().includes('MAC');
                const isPrintShortcut = (isMac && event.metaKey && event.key.toLowerCase() === 'p')
                    || (!isMac && event.ctrlKey && event.key.toLowerCase() === 'p');

                if (!isPrintShortcut) {
                    return;
                }

                event.preventDefault();
                triggerPrint();
            });
        })();
    </script>
</body>
</html>
