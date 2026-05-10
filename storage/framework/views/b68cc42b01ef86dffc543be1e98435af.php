<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impresion ODC <?php echo e($ordenCompra->correlativo_odc ?? $ordenCompra->id); ?></title>
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
        <div class="title">Orden de Compra: <?php echo e($ordenCompra->correlativo_odc ?? $ordenCompra->id); ?></div>
        <div class="hint">Selecciona el formato que quieres ver antes de imprimir o guardar.</div>
        <div class="variant-switcher">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $variantOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $variantKey => $variant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <button
                    class="btn <?php echo e($selectedVariant === $variantKey ? 'btn-primary active' : 'btn-secondary'); ?>"
                    type="button"
                    data-variant="<?php echo e($variantKey); ?>"
                    data-pdf-url="<?php echo e($variant['pdfUrl']); ?>"
                    data-download-url="<?php echo e($variant['downloadUrl']); ?>"
                    data-excel-url="<?php echo e($variant['excelUrl']); ?>"
                >
                    <?php echo e($variant['label']); ?>

                </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <a id="downloadPdfBtn" class="btn btn-secondary" href="<?php echo e($variantOptions[$selectedVariant]['downloadUrl']); ?>">Descargar PDF</a>
        <a id="downloadExcelBtn" class="btn btn-secondary" href="<?php echo e($variantOptions[$selectedVariant]['excelUrl']); ?>">Descargar Excel</a>
        <button id="printBtn" class="btn btn-primary" type="button">Imprimir / Guardar PDF</button>
    </div>

    <div class="canvas">
        <iframe id="pdfFrame" src="<?php echo e($variantOptions[$selectedVariant]['pdfUrl']); ?>" title="Vista previa PDF ODC"></iframe>
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

                frame.src = button.dataset.pdfUrl;

                if (downloadPdfBtn) {
                    downloadPdfBtn.href = button.dataset.downloadUrl || '#';
                }

                if (downloadExcelBtn) {
                    downloadExcelBtn.href = button.dataset.excelUrl || '#';
                }

                setActiveButton(button);
            }

            function triggerPrint() {
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
                const selectedButton = variantButtons.find((button) => button.dataset.variant === '<?php echo e($selectedVariant); ?>') || variantButtons[0];
                updateVariant(selectedButton);
            }
        })();
    </script>
</body>
</html>
<?php /**PATH C:\laragon\www\SISTEMA-OFICIAL-AGARCORP\resources\views/ordenes-compra/print-preview.blade.php ENDPATH**/ ?>