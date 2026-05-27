<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impresion Sumario {{ $sumario->correlativo_sdc ?? $sumario->id }}</title>
    <style>
        :root {
            --bg: #f6f8fb;
            --panel: #ffffff;
            --ink: #1f2937;
            --muted: #64748b;
            --accent: #0f766e;
            --accent-dark: #0b5f58;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top left, #ffffff 0%, var(--bg) 60%);
            color: var(--ink);
            min-height: 100vh;
            overflow: hidden;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            min-height: 64px;
            padding: 0.7rem 1rem;
            border-bottom: 1px solid #dbe3ef;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(4px);
        }

        .title { margin-right: auto; font-weight: 600; letter-spacing: 0.01em; }
        .hint { color: var(--muted); font-size: 0.9rem; }

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

        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-dark); }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        .btn-secondary:hover { background: #cbd5e1; }

        .canvas {
            height: calc(100vh - 64px);
            padding: 0;
            background: #e5e7eb;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: 0;
            border-radius: 0;
            background: #fff;
        }

        @media print {
            .toolbar { display: none !important; }
            .canvas { padding: 0; }
            iframe { border: 0; border-radius: 0; height: 100vh; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="title">Sumario: {{ $sumario->correlativo_sdc ?? $sumario->id }}</div>
        <div class="hint">Usa este boton para Imprimir o Guardar como PDF</div>
        <a class="btn btn-secondary" href="{{ $downloadUrl }}">Descargar PDF</a>
        <a class="btn btn-secondary" href="{{ $excelUrl }}">Descargar Excel</a>
        <button id="printBtn" class="btn btn-primary" type="button">Imprimir / Guardar PDF</button>
    </div>

    <div class="canvas">
        <iframe id="pdfFrame" src="{{ $pdfUrl }}#view=FitH&zoom=125" title="Vista previa PDF Sumario"></iframe>
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
