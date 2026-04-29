<?php if (isset($component)) { $__componentOriginal166a02a7c5ef5a9331faf66fa665c256 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal166a02a7c5ef5a9331faf66fa665c256 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament-panels::components.page.index','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament-panels::page'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="grid gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <h2 class="text-lg font-semibold text-slate-900">Modulos con Notificaciones</h2>
            <p class="mt-1 text-sm text-slate-600">
                Ventanas monitoreadas: <?php echo e($notificationModules); ?> | Con pendientes visibles: <?php echo e($activeModules); ?> | Modulos con pendientes: <?php echo e($pendingModules); ?>

            </p>
            <p class="mt-2 text-sm text-slate-500">
                Aqui aparecen los modulos que usan este esquema de notificaciones. Si un modulo esta en 0, sigue listado pero sin pendientes visibles por atender.
            </p>

            <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $moduleTotals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?php echo e($module['module']); ?></div>
                        <div class="mt-1 text-lg font-semibold <?php echo e($module['hasPending'] ? 'text-amber-600' : 'text-slate-700'); ?>">
                            <?php echo e($module['count']); ?>

                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Bandeja por modulo</h3>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <a
                        href="<?php echo e($module['url']); ?>"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-primary-300 hover:bg-white"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500"><?php echo e($module['module']); ?></div>
                                <div class="mt-1 text-base font-semibold text-slate-900"><?php echo e($module['label']); ?></div>
                            </div>
                            <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                'inline-flex min-w-10 items-center justify-center rounded-full px-3 py-1 text-sm font-semibold',
                                'bg-slate-200 text-slate-700' => $module['badgeColor'] === 'gray',
                                'bg-amber-100 text-amber-700' => $module['badgeColor'] === 'warning',
                                'bg-rose-100 text-rose-700' => $module['badgeColor'] === 'danger',
                                'bg-emerald-100 text-emerald-700' => $module['badgeColor'] === 'success',
                                'bg-sky-100 text-sky-700' => ! in_array($module['badgeColor'], ['gray', 'warning', 'danger', 'success'], true),
                            ]); ?>">
                                <?php echo e($module['count']); ?>

                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-600"><?php echo e($module['description']); ?></p>
                        <p class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                            <?php echo e($module['hasPending'] ? 'Con pendientes visibles' : 'Sin pendientes visibles'); ?>

                        </p>
                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-600 md:col-span-2 xl:col-span-3">
                        No hay modulos configurados para este esquema de notificaciones en tu perfil actual.
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Ultimos eventos</h3>
            <p class="mt-1 text-sm text-slate-600">
                Historial total: <?php echo e($total); ?> | No leidas: <?php echo e($unread); ?>

            </p>
            <div class="mt-3 grid gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $latest; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <?php ($data = $item->data ?? []); ?>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <div class="text-sm font-semibold text-slate-900"><?php echo e($data['title'] ?? 'Notificacion'); ?></div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($data['body'])): ?>
                            <div class="mt-1 text-sm text-slate-600"><?php echo e($data['body']); ?></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div class="mt-1 text-xs text-slate-500"><?php echo e(optional($item->created_at)->format('d/m/Y H:i')); ?></div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-600">
                        No tienes notificaciones registradas.
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <p class="mt-3 text-sm text-slate-500">
                Puedes limpiar todo el historial con tu clave de inicio de sesion desde el boton superior.
            </p>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal166a02a7c5ef5a9331faf66fa665c256)): ?>
<?php $attributes = $__attributesOriginal166a02a7c5ef5a9331faf66fa665c256; ?>
<?php unset($__attributesOriginal166a02a7c5ef5a9331faf66fa665c256); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal166a02a7c5ef5a9331faf66fa665c256)): ?>
<?php $component = $__componentOriginal166a02a7c5ef5a9331faf66fa665c256; ?>
<?php unset($__componentOriginal166a02a7c5ef5a9331faf66fa665c256); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\SISTEMA-OFICIAL-AGARCORP\resources\views\filament\pages\notification-center.blade.php ENDPATH**/ ?>