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

     <?php $__env->slot('header', null, []); ?> 
        <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-200">
            Información Empresa > AGARCORP
        </h2>
     <?php $__env->endSlot(); ?>

    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Razon social</p>
                    <p class="mt-1 text-base text-gray-900 dark:text-gray-100"><?php echo e($this->record->razon_social ?: '-'); ?></p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">RIF</p>
                    <p class="mt-1 text-base text-gray-900 dark:text-gray-100"><?php echo e($this->record->rif ?: '-'); ?></p>
                </div>

                <div class="md:col-span-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Direccion fiscal</p>
                    <p class="mt-1 text-base text-gray-900 dark:text-gray-100"><?php echo e($this->record->direccion_fiscal ?: '-'); ?></p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Telefono principal</p>
                    <p class="mt-1 text-base text-gray-900 dark:text-gray-100"><?php echo e($this->record->telefono_principal ?: '-'); ?></p>
                </div>
            </div>

            <div class="mt-8">
                <a
                    href="<?php echo e(\App\Filament\Resources\InformacionAgarcorp\InformacionAgarcorpResource::getUrl('edit', ['record' => $this->record])); ?>"
                    class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-500"
                >
                    Editar
                </a>
            </div>
        </section>
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
<?php /**PATH C:\laragon\www\SISTEMA-OFICIAL-AGARCORP\resources\views\filament\resources\informacion-agarcorp\pages\list-informacion-agarcorps.blade.php ENDPATH**/ ?>