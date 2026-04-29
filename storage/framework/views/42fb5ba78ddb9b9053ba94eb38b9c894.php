<?php if (isset($component)) { $__componentOriginalb525200bfa976483b4eaa0b7685c6e24 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb525200bfa976483b4eaa0b7685c6e24 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament-widgets::components.widget','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament-widgets::widget'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="ag-desktop-shell">
        <section class="ag-desktop-hero">
            <div class="ag-desktop-hero__copy">
                <p class="ag-desktop-kicker">Escritorio principal</p>
                <h1 class="ag-desktop-title"><?php echo e($greeting); ?>, <?php echo e($userName); ?></h1>
                <p class="ag-desktop-subtitle">
                    Revisa tu perfil operativo y usa el menu lateral para entrar rapido a tus modulos.
                </p>

                <div class="ag-desktop-tags">
                    <span><?php echo e($today); ?></span>
                    <span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bcvRateValue): ?>
                            Tasa BCV del dia (<?php echo e($bcvRateValue); ?>)
                        <?php else: ?>
                            Tasa BCV del dia (sin tasa cargada)
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </span>
                </div>
            </div>

            <div class="ag-desktop-hero__panel">
                <div class="ag-desktop-panel-card">
                    <div class="ag-desktop-panel-label">Tu perfil operativo</div>
                    <div class="ag-desktop-panel-value"><?php echo e($department); ?></div>
                    <p class="ag-desktop-panel-text">
                        <?php echo e($cargo); ?>

                    </p>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($lastVisitedModule['url'])): ?>
                        <div class="ag-desktop-panel-module">
                            <div class="ag-desktop-panel-module__eyebrow">Ultimo modulo abierto</div>
                            <div class="ag-desktop-panel-module__title"><?php echo e($lastVisitedModule['title'] ?? 'Modulo reciente'); ?></div>
                            <a class="ag-desktop-panel-module__link" href="<?php echo e($lastVisitedModule['url']); ?>">
                                Abrir de nuevo
                            </a>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </section>

    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb525200bfa976483b4eaa0b7685c6e24)): ?>
<?php $attributes = $__attributesOriginalb525200bfa976483b4eaa0b7685c6e24; ?>
<?php unset($__attributesOriginalb525200bfa976483b4eaa0b7685c6e24); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb525200bfa976483b4eaa0b7685c6e24)): ?>
<?php $component = $__componentOriginalb525200bfa976483b4eaa0b7685c6e24; ?>
<?php unset($__componentOriginalb525200bfa976483b4eaa0b7685c6e24); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\SISTEMA-OFICIAL-AGARCORP\resources\views\filament\widgets\dashboard\desktop-welcome-widget.blade.php ENDPATH**/ ?>