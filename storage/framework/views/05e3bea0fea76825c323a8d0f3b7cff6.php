<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud de Compra #<?php echo e($solicitudCompra->id); ?></title>
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
    <div class="muted">Documento #<?php echo e($solicitudCompra->id); ?> | Estado: <?php echo e($solicitudCompra->estado ?? 'Pendiente'); ?></div>

    <div class="section">
        <table class="meta">
            <tr>
                <td>
                    <span class="label">Codigo de Control</span>
                    <?php echo e($solicitudCompra->codigo_control ?: '-'); ?>

                </td>
                <td>
                    <span class="label">Codigo Control Procura</span>
                    <?php echo e($solicitudCompra->codigo_control_procura ?: '-'); ?>

                </td>
                <td>
                    <span class="label">Fecha Solicitud</span>
                    <?php echo e(optional($solicitudCompra->fecha_solicitud)->format('d/m/Y') ?: '-'); ?>

                </td>
                <td>
                    <span class="label">Prioridad</span>
                    <?php echo e($solicitudCompra->prioridad ?: '-'); ?>

                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Departamento Solicitante</span>
                    <?php echo e($solicitudCompra->departamento_solicitante ?: '-'); ?>

                </td>
                <td>
                    <span class="label">Tipo de Solicitud</span>
                    <?php echo e($solicitudCompra->tipo_solicitud ?: '-'); ?>

                </td>
                <td>
                    <span class="label">Centro</span>
                    <?php echo e($solicitudCompra->centro ?: '-'); ?>

                </td>
                <td>
                    <span class="label">Cuenta</span>
                    <?php echo e($solicitudCompra->cuenta ?: '-'); ?>

                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Elemento</span>
                    <?php echo e($solicitudCompra->elemento ?: '-'); ?>

                </td>
                <td colspan="3">
                    <span class="label">Contrato</span>
                    <?php echo e($solicitudCompra->contrato ?: '-'); ?>

                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <span class="label">Para ser usado en</span>
                    <?php echo e($solicitudCompra->para_ser_usado_en ?: '-'); ?>

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
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $solicitudCompra->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <tr>
                        <td><?php echo e($item->item ?: $loop->iteration); ?></td>
                        <td><?php echo e($item->descripcion ?: '-'); ?></td>
                        <td><?php echo e($item->unidad_medida ?: '-'); ?></td>
                        <td class="text-right"><?php echo e($item->cantidad_solicitada ?: '-'); ?></td>
                        <td class="text-right"><?php echo e($item->cantidad_existencia ?: '-'); ?></td>
                        <td class="text-right"><?php echo e($item->cantidad_a_comprar ?: '-'); ?></td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr>
                        <td colspan="6" class="muted">No hay items cargados.</td>
                    </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <table class="meta">
            <tr>
                <td>
                    <span class="label">Solicitado por</span>
                    <?php echo e($solicitudCompra->solicitadoPor?->name ?: '-'); ?>

                    <div class="small muted">Cargo: <?php echo e($solicitudCompra->cargo_solicitante ?: '-'); ?></div>
                    <div class="small muted">Fecha: <?php echo e(optional($solicitudCompra->fecha_solicitante)->format('d/m/Y') ?: '-'); ?></div>
                </td>
                <td>
                    <span class="label">Por almacen</span>
                    <?php echo e($solicitudCompra->porAlmacen?->name ?: '-'); ?>

                    <div class="small muted">Cargo: <?php echo e($solicitudCompra->cargo_almacen ?: '-'); ?></div>
                    <div class="small muted">Fecha: <?php echo e(optional($solicitudCompra->fecha_almacen)->format('d/m/Y') ?: '-'); ?></div>
                </td>
                <td>
                    <span class="label">Aprobado por</span>
                    <?php echo e($solicitudCompra->aprobadoPor?->name ?: '-'); ?>

                    <div class="small muted">Cargo: <?php echo e($solicitudCompra->cargo_aprobador ?: '-'); ?></div>
                    <div class="small muted">Fecha: <?php echo e(optional($solicitudCompra->fecha_aprobador)->format('d/m/Y') ?: '-'); ?></div>
                </td>
                <td>
                    <span class="label">Recibido por</span>
                    <?php echo e($solicitudCompra->recibidoPor?->name ?: '-'); ?>

                    <div class="small muted">Cargo: <?php echo e($solicitudCompra->cargo_receptor ?: '-'); ?></div>
                    <div class="small muted">Fecha: <?php echo e(optional($solicitudCompra->fecha_receptor)->format('d/m/Y') ?: '-'); ?></div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
<?php /**PATH C:\laragon\www\SISTEMA-OFICIAL-AGARCORP\resources\views\solicitudes-compra\formato-pdf.blade.php ENDPATH**/ ?>