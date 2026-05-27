<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impresion ODC {{ $ordenCompra->correlativo_odc ?? $ordenCompra->id }}</title>
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
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            padding: 0.85rem 1rem;
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
        .btn.active { box-shadow: inset 0 0 0 2px rgba(15, 118, 110, 0.25); }

        .variant-switcher {
            display: inline-flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .canvas { padding: 0.9rem; }

        iframe {
            width: 100%;
            height: calc(100vh - 82px);
            border: 1px solid #dbe3ef;
            border-radius: 12px;
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
        <div class="title">Orden de Compra: {{ $ordenCompra->correlativo_odc ?? $ordenCompra->id }}</div>
        <div class="hint">Selecciona el formato que quieres ver antes de imprimir o guardar.</div>
        <div class="variant-switcher">
            @foreach ($variantOptions as $variantKey => $variant)
                <button
                    class="btn {{ $selectedVariant === $variantKey ? 'btn-primary active' : 'btn-secondary' }}"
                    type="button"
                    data-variant="{{ $variantKey }}"
                    data-pdf-url="{{ $variant['pdfUrl'] }}"
                    data-download-url="{{ $variant['downloadUrl'] }}"
                    data-excel-url="{{ $variant['excelUrl'] }}"
                >
                    {{ $variant['label'] }}
                </button>
            @endforeach
        </div>
        <a id="downloadPdfBtn" class="btn btn-secondary" href="{{ $variantOptions[$selectedVariant]['downloadUrl'] }}">Descargar PDF</a>
        <a id="downloadExcelBtn" class="btn btn-secondary" href="{{ $variantOptions[$selectedVariant]['excelUrl'] }}">Descargar Excel</a>
        <button id="printBtn" class="btn btn-primary" type="button">Imprimir / Guardar PDF</button>
    </div>

    <div class="canvas">
        <iframe id="pdfFrame" src="about:blank" title="Vista previa PDF ODC"></iframe>
    </div>

    <script>
        (function () {
            const frame = document.getElementById('pdfFrame');
            const printBtn = document.getElementById('printBtn');
            const downloadPdfBtn = document.getElementById('downloadPdfBtn');
            const downloadExcelBtn = document.getElementById('downloadExcelBtn');
            const variantButtons = Array.from(document.querySelectorAll('[data-variant]'));

            function setActiveButton(activeButton) {
                variantButtons.forEach((button) => {
                    const active = button === activeButton;
                    button.classList.toggle('active', active);
                    button.classList.toggle('btn-primary', active);
                    button.classList.toggle('btn-secondary', !active);
                });
            }

            function updateVariant(button) {
                if (!button || !frame) {
                    return;
                }

                const nextUrl = (button.dataset.pdfUrl || '') + '#zoom=page-width';

                if (frame.src !== nextUrl) {
                    frame.src = nextUrl;
                }

                if (downloadPdfBtn) {
                    downloadPdfBtn.href = button.dataset.downloadUrl || '#';
                }

                if (downloadExcelBtn) {
                    downloadExcelBtn.href = button.dataset.excelUrl || '#';
                }

                setActiveButton(button);
            }

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

            variantButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    updateVariant(button);
                });
            });

            if (variantButtons.length > 0) {
                const selectedButton = variantButtons.find((button) => button.dataset.variant === '{{ $selectedVariant }}') || variantButtons[0];
                updateVariant(selectedButton);
            }

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
