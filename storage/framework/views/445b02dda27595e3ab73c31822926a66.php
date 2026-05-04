<section class="dw-shell">
    <style>
        .dw-shell { width: 100%; max-width: 100%; margin: 0 auto; }
        .dw-grid-layout { display: block; }
        .dw-card {
            border: 1px solid rgba(17, 32, 52, 0.19);
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 18px 45px rgba(10, 32, 57, 0.14);
            padding: 18px;
        }
        .dw-side-title { margin: 0 0 2px; font-size: 18px; font-weight: 900; color: #142541; }
        .dw-side-subtitle { margin: 0 0 10px; color: #58708f; font-size: 13px; }
        .dw-side-list { display: grid; gap: 8px; }
        .dw-side-controls {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            align-items: end;
            margin-bottom: 12px;
        }
        .dw-side-date-label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 800;
            color: #29415f;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .dw-side-date-input {
            width: 100%;
            border: 1px solid #cddbeb;
            border-radius: 12px;
            background: #f9fbff;
            padding: 10px 12px;
            color: #13253f;
            font-size: 14px;
            font-weight: 700;
        }
        .dw-side-date-input:focus {
            outline: none;
            border-color: #2e60cc;
            box-shadow: 0 0 0 3px rgba(46, 96, 204, 0.16);
        }
        .dw-side-count {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
            border-radius: 999px;
            background: #eef5ff;
            color: #22457d;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 800;
        }
        .dw-side-scroll {
            max-height: calc(100vh - 320px);
            overflow-y: auto;
            padding-right: 4px;
        }
        .dw-side-item {
            border: 1px solid #d4e0ef;
            border-radius: 12px;
            background: #f9fcff;
            padding: 9px 10px;
        }
        .dw-side-top { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 3px; }
        .dw-side-material { font-size: 13px; font-weight: 700; color: #223d5e; margin: 0; }
        .dw-side-user { font-size: 12px; color: #4f6885; margin: 0; }
        .dw-side-meta { display: flex; gap: 9px; margin-top: 4px; flex-wrap: wrap; }
        .dw-pill { border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 700; }
        .dw-pill.qty { background: #eaf2ff; color: #214f9d; }
        .dw-pill.time { background: #fff2d8; color: #8b5d02; }
        .dw-pill.pending { background: #fff6da; color: #8b5d02; }
        .dw-pill.approved { background: #eafcee; color: #16673a; }
        .dw-pill.rejected { background: #ffe8e8; color: #8e2323; }
        .dw-side-empty { border: 1px dashed #c9d8ea; border-radius: 12px; padding: 12px; text-align: center; color: #5f7693; font-size: 13px; }
        .dw-header { display: flex; justify-content: space-between; gap: 12px; align-items: end; margin-bottom: 14px; flex-wrap: wrap; }
        .dw-title { margin: 0; font-size: 31px; font-weight: 900; color: #12203a; letter-spacing: -0.02em; }
        .dw-subtitle { margin: 4px 0 0; font-size: 14px; color: #53657c; }
        .dw-chip { border: 1px solid rgba(17, 32, 52, 0.2); border-radius: 999px; padding: 7px 12px; font-size: 12px; font-weight: 700; color: #1e3557; background: #eef5ff; }
        .dw-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .dw-col-span-2 { grid-column: span 2; }
        .dw-label { display: block; margin-bottom: 6px; font-weight: 700; color: #273c5c; }
        .dw-input-wrap { position: relative; }
        .dw-input {
            width: 100%; border: 1px solid #b8c7da; border-radius: 12px; background: #fff; padding: 11px 12px;
            font-size: 15px; color: #0f2138; transition: box-shadow .15s ease, border-color .15s ease;
        }
        .dw-input.has-clear { padding-right: 40px; }
        .dw-clear-btn {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            width: 22px;
            height: 22px;
            border: 0;
            border-radius: 999px;
            background: #d9e2ef;
            color: #284262;
            font-size: 14px;
            font-weight: 800;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .dw-clear-btn:hover { background: #c9d6e8; }
        .dw-input:focus { outline: none; border-color: #2e60cc; box-shadow: 0 0 0 3px rgba(46, 96, 204, 0.2); }
        .dw-suggestions { margin-top: 6px; max-height: 220px; overflow-y: auto; border: 1px solid #d5dfec; border-radius: 12px; background: #fff; }
        .dw-suggestion-btn { width: 100%; border: 0; background: #fff; text-align: left; padding: 10px 12px; cursor: pointer; }
        .dw-suggestion-btn:hover { background: #f1f6ff; }
        .dw-suggestion-empty {
            margin-top: 6px;
            border: 1px dashed #d5dfec;
            border-radius: 12px;
            background: #f9fbff;
            padding: 10px 12px;
            font-size: 13px;
            color: #4f6885;
        }
        .dw-inline { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .dw-stock { font-size: 12px; font-weight: 700; color: #0d7b43; }
        .dw-checkbox-wrap { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #d5dfec; border-radius: 12px; background: #f5f8fd; padding: 10px 12px; font-weight: 600; color: #1f3555; }
        .dw-return-row { display: grid; grid-template-columns: auto minmax(260px, 1fr); gap: 12px; align-items: end; }
        .dw-material-config { display: grid; grid-template-columns: minmax(180px, 1fr) auto minmax(260px, 1fr); gap: 12px; align-items: end; }
        .dw-label-row { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
        .dw-return-note {
            font-size: 12px;
            font-weight: 700;
            color: #12723f;
            background: #e8f8ef;
            border: 1px solid #b6e5ca;
            border-radius: 999px;
            padding: 3px 9px;
            line-height: 1.2;
        }
        .dw-submit { width: 100%; border: 0; border-radius: 12px; padding: 13px 14px; color: #fff; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; background: linear-gradient(135deg, #2459d4, #163e99); cursor: pointer; }
        .dw-submit:hover { filter: brightness(1.04); }
        .dw-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(10, 22, 39, 0.58);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 140;
        }
        .dw-modal {
            width: min(100%, 480px);
            border-radius: 24px;
            background: linear-gradient(180deg, #ffffff 0%, #f4f8ff 100%);
            border: 1px solid rgba(116, 142, 181, 0.32);
            box-shadow: 0 28px 80px rgba(8, 24, 44, 0.28);
            overflow: hidden;
        }
        .dw-modal-head {
            padding: 22px 24px 12px;
            background: radial-gradient(circle at top left, rgba(57, 116, 231, 0.18), transparent 52%), linear-gradient(135deg, #eff5ff, #ffffff);
        }
        .dw-modal-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eaf1ff;
            color: #1a4ba8;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .dw-modal-title {
            margin: 14px 0 6px;
            font-size: 28px;
            font-weight: 900;
            color: #12203a;
            letter-spacing: -0.03em;
        }
        .dw-modal-text {
            margin: 0;
            color: #24364d;
            font-size: 14px;
            line-height: 1.5;
        }
        .dw-modal-body { padding: 18px 24px 24px; }
        .dw-modal-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 16px;
        }
        .dw-btn-secondary {
            border: 1px solid #efb0b0;
            border-radius: 12px;
            background: #fff1f1;
            color: #a12626;
            font-weight: 800;
            padding: 12px 14px;
            cursor: pointer;
        }
        .dw-btn-secondary:hover { background: #ffe3e3; }
        .dw-add-btn {
            width: 100%;
            margin-top: 8px;
            border: 1px solid #1f5bd8;
            border-radius: 10px;
            background: #edf3ff;
            color: #173f95;
            padding: 9px 12px;
            font-weight: 800;
            letter-spacing: .02em;
            cursor: pointer;
        }
        .dw-add-btn:hover { background: #e2edff; }
        .dw-items-box {
            border: 1px solid #d5dfec;
            border-radius: 12px;
            background: #fbfdff;
            overflow: hidden;
        }
        .dw-items-head,
        .dw-item-row {
            display: grid;
            grid-template-columns: 150px 1fr 90px 120px 130px 90px;
            gap: 10px;
            align-items: center;
            padding: 9px 12px;
        }
        .dw-items-head {
            background: #eef4ff;
            font-size: 12px;
            font-weight: 800;
            color: #1f3555;
        }
        .dw-item-row { border-top: 1px solid #e3eaf5; }
        .dw-item-actions { text-align: right; }
        .dw-item-remove {
            border: 0;
            border-radius: 8px;
            background: #ffe8e8;
            color: #8e2323;
            font-weight: 700;
            padding: 6px 10px;
            cursor: pointer;
        }
        .dw-item-remove:hover { background: #ffd7d7; }
        .dw-items-empty { padding: 12px; font-size: 13px; color: #5f7693; }
        .dw-error { margin-top: 5px; color: #b12626; font-size: 12px; font-weight: 600; }
        .dw-mobile-label { display: none; }
        .dw-history-panel {
            position: fixed;
            top: 110px;
            right: 0;
            width: min(430px, calc(100vw - 12px));
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            box-shadow: -18px 18px 45px rgba(10, 32, 57, 0.14);
            z-index: 60;
            opacity: 1;
            transform: translateY(0) scale(1);
            transform-origin: top right;
            transition: opacity .22s ease, transform .22s ease;
        }
        .dw-history-panel.hidden {
            opacity: 0;
            transform: translateY(-10px) scale(0.985);
            pointer-events: none;
        }
        @media (min-width: 1500px) {
            .dw-card { padding: 24px; border-radius: 20px; }
            .dw-title { font-size: 40px; }
            .dw-subtitle { font-size: 17px; }
            .dw-chip { font-size: 14px; padding: 9px 16px; }
            .dw-label { font-size: 22px; margin-bottom: 8px; }
            .dw-input { font-size: 18px; padding: 14px 16px; border-radius: 14px; }
            .dw-input.has-clear { padding-right: 50px; }
            .dw-clear-btn { width: 26px; height: 26px; right: 12px; font-size: 16px; }
            .dw-submit { font-size: 22px; padding: 16px 18px; border-radius: 14px; letter-spacing: .06em; }
            .dw-grid { gap: 18px; }
            .dw-checkbox-wrap { font-size: 20px; padding: 12px 16px; border-radius: 14px; }
            .dw-return-row { gap: 16px; }
            .dw-error { font-size: 14px; }
        }
        @media (max-width: 900px) {
            .dw-history-panel {
                top: 90px;
                right: 12px;
                left: 12px;
                width: auto;
                border-top-right-radius: 18px;
                border-bottom-right-radius: 18px;
                box-shadow: 0 18px 45px rgba(10, 32, 57, 0.14);
            }
            .dw-side-scroll { max-height: 320px; }
            .dw-grid { grid-template-columns: 1fr; }
            .dw-col-span-2 { grid-column: auto; }
            .dw-return-row { grid-template-columns: 1fr; align-items: stretch; }
            .dw-material-config { grid-template-columns: 1fr; align-items: stretch; }
            .dw-title { font-size: 25px; }
            .dw-modal { width: 100%; }
            .dw-modal-title { font-size: 24px; }
            .dw-modal-actions { grid-template-columns: 1fr; }

            .dw-items-head {
                display: none;
            }

            .dw-item-row {
                grid-template-columns: 1fr;
                gap: 6px;
                padding: 12px;
            }

            .dw-item-row > span,
            .dw-item-row > .dw-item-actions {
                display: block;
                width: 100%;
            }

            .dw-mobile-label {
                display: inline-block;
                margin-right: 6px;
                font-size: 11px;
                font-weight: 800;
                color: #274264;
                text-transform: uppercase;
                letter-spacing: .04em;
            }

            .dw-item-actions {
                text-align: left;
                margin-top: 4px;
            }

            .dw-item-remove {
                width: 100%;
            }
        }
    </style>

    <div class="dw-grid-layout" wire:poll.10s>
    <div class="dw-card">
        <div id="kiosk-success" class="kiosk-success hidden" wire:ignore></div>

        <div class="dw-header">
            <div>
                <h1 class="dw-title">Retiro Diario de Almacen</h1>
                <p class="dw-subtitle">Registro rapido de retiro con validacion de contraseña al momento de solicitar.</p>
            </div>
            <span class="dw-chip">Solo materiales con stock disponible</span>
        </div>

        <form wire:submit="openRegisterModal" class="dw-grid">
            <div class="dw-col-span-2">
                <label for="productSearch" class="dw-label">Buscador de Materiales</label>
                <div class="dw-input-wrap">
                    <input
                        id="productSearch"
                        type="text"
                        wire:model.live.debounce.250ms="productSearch"
                        wire:keydown.enter.prevent="selectSingleProductSuggestion"
                        wire:focus="openProductSuggestions"
                        wire:blur="closeProductSuggestions"
                        autocomplete="off"
                        class="dw-input has-clear"
                        placeholder="SKU o descripcion"
                        autofocus
                    >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($productSearch !== ''): ?>
                        <button type="button" class="dw-clear-btn" wire:click="clearField('productSearch')" aria-label="Limpiar material">X</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showProductSuggestions && ! $product_id): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->productSuggestions->isNotEmpty()): ?>
                        <ul class="dw-suggestions">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->productSuggestions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <li>
                                    <button
                                        type="button"
                                        wire:mousedown.prevent="selectProduct(<?php echo e($product->id); ?>)"
                                        wire:click="selectProduct(<?php echo e($product->id); ?>)"
                                        class="dw-suggestion-btn"
                                    >
                                        <span class="dw-inline">
                                            <span><?php echo e($product->sku); ?> - <?php echo e($product->descripcion); ?></span>
                                            <span class="dw-stock">Stock: <?php echo e($product->stock_actual); ?></span>
                                        </span>
                                    </button>
                                </li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </ul>
                    <?php elseif(trim($productSearch) !== ''): ?>
                        <div class="dw-suggestion-empty">
                            No hay materiales con stock para "<?php echo e($productSearch); ?>". Puede estar sin stock o no existir.
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['product_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="dw-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="dw-col-span-2 dw-material-config">
                <div>
                    <label for="quantity" class="dw-label">Cantidad</label>
                    <div class="dw-input-wrap">
                        <input
                            id="quantity"
                            type="number"
                            step="1"
                            min="1"
                            wire:model="quantity"
                            class="dw-input has-clear"
                            placeholder="1"
                        >
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($quantity !== ''): ?>
                            <button type="button" class="dw-clear-btn" wire:click="clearField('quantity')" aria-label="Limpiar cantidad">X</button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['quantity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="dw-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <div class="dw-label-row">
                        <label for="return_date" class="dw-label" style="margin-bottom: 0;">Fecha de retorno</label>
                        <span class="dw-return-note">Dejar en blanco si no requiere retorno</span>
                    </div>
                    <div class="dw-input-wrap">
                        <input
                            id="return_date"
                            type="date"
                            wire:model="return_date"
                            min="<?php echo e(now()->toDateString()); ?>"
                            class="dw-input has-clear"
                            onclick="if (this.showPicker) this.showPicker()"
                            onfocus="if (this.showPicker) this.showPicker()"
                        >
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($return_date)): ?>
                            <button type="button" class="dw-clear-btn" wire:click="clearField('return_date')" aria-label="Limpiar fecha de retorno">X</button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['return_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="dw-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <label class="dw-checkbox-wrap">
                    <input type="checkbox" wire:model="requires_return">
                    Requiere retorno
                </label>
            </div>

            <div class="dw-col-span-2">
                <button type="button" class="dw-add-btn" wire:click="addItem">Agregar material</button>
            </div>

            <div>
                <label for="userSearch" class="dw-label">Nombre de Solicitante</label>
                <div class="dw-input-wrap">
                    <input
                        id="userSearch"
                        type="text"
                        wire:model.live.debounce.250ms="userSearch"
                        wire:focus="openUserSuggestions"
                        wire:blur="closeUserSuggestions"
                        autocomplete="off"
                        class="dw-input has-clear"
                        placeholder="Nombre o correo"
                    >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($userSearch !== ''): ?>
                        <button type="button" class="dw-clear-btn" wire:click="clearField('userSearch')" aria-label="Limpiar solicitante">X</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showUserSuggestions && $this->userSuggestions->isNotEmpty()): ?>
                    <ul class="dw-suggestions">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->userSuggestions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <li>
                                <button
                                    type="button"
                                    wire:mousedown.prevent="selectUser(<?php echo e($user->id); ?>)"
                                    wire:click="selectUser(<?php echo e($user->id); ?>)"
                                    class="dw-suggestion-btn"
                                >
                                    <span style="display:block; font-weight:700; color:#203a5b;"><?php echo e($user->name); ?></span>
                                    <span style="display:block; font-size:12px; color:#607188;"><?php echo e($user->email); ?></span>
                                </button>
                            </li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </ul>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['user_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="dw-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label for="destination" class="dw-label">Destino</label>
                <div class="dw-input-wrap">
                    <input
                        id="destination"
                        type="text"
                        wire:model="destination"
                        class="dw-input has-clear"
                        placeholder="Area o ubicacion"
                    >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($destination !== ''): ?>
                        <button type="button" class="dw-clear-btn" wire:click="clearField('destination')" aria-label="Limpiar destino">X</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['destination'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="dw-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="dw-col-span-2">
                <label class="dw-label">Materiales a retirar (<?php echo e(count($items)); ?>)</label>
                <div class="dw-items-box">
                    <div class="dw-items-head">
                        <span>SKU</span>
                        <span>Descripcion</span>
                        <span>Cantidad</span>
                        <span>Retorno</span>
                        <span>Fecha retorno</span>
                        <span style="text-align:right;">Accion</span>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <div class="dw-item-row">
                            <span><span class="dw-mobile-label">SKU:</span><?php echo e($item['sku']); ?></span>
                            <span><span class="dw-mobile-label">Descripcion:</span><?php echo e($item['descripcion']); ?></span>
                            <span><span class="dw-mobile-label">Cantidad:</span><?php echo e($item['quantity']); ?></span>
                            <span><span class="dw-mobile-label">Retorno:</span><?php echo e($item['requires_return'] ? 'Retorna' : 'No retorna'); ?></span>
                            <span>
                                <span class="dw-mobile-label">Fecha retorno:</span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['requires_return'] && ! empty($item['return_date'])): ?>
                                    <?php echo e(\Illuminate\Support\Carbon::parse($item['return_date'])->format('d/m/Y')); ?>

                                <?php else: ?>
                                    No retorna
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>
                            <div class="dw-item-actions">
                                <button type="button" class="dw-item-remove" wire:click="removeItem(<?php echo e($index); ?>)">Quitar</button>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="dw-items-empty">Aun no has agregado materiales al retiro.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['items'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="dw-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="dw-col-span-2">
                <button
                    type="submit"
                    class="dw-submit"
                >
                    Registrar
                </button>
            </div>
        </form>
    </div>

    <aside id="kiosk-history-panel" class="dw-card dw-history-panel hidden">
        <h2 class="dw-side-title">Enviados por dia</h2>
        <p class="dw-side-subtitle">Consulta todos los retiros registrados en la fecha que selecciones.</p>

        <div class="dw-side-controls">
            <div>
                <label for="historyDate" class="dw-side-date-label">Fecha</label>
                <input
                    id="historyDate"
                    type="date"
                    wire:model.live="historyDate"
                    max="<?php echo e(now()->toDateString()); ?>"
                    class="dw-side-date-input"
                    onclick="if (this.showPicker) this.showPicker()"
                    onfocus="if (this.showPicker) this.showPicker()"
                >
            </div>
        </div>

        <div class="dw-side-count">
            <span><?php echo e($this->filteredWithdrawals->count()); ?></span>
            <span><?php echo e('registro' . ($this->filteredWithdrawals->count() === 1 ? '' : 's')); ?></span>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->filteredWithdrawals->isEmpty()): ?>
            <div class="dw-side-empty">No hay retiros registrados para la fecha seleccionada.</div>
        <?php else: ?>
            <div class="dw-side-scroll">
                <div class="dw-side-list">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->filteredWithdrawals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <article class="dw-side-item">
                            <div class="dw-side-top">
                                <p class="dw-side-material"><?php echo e($item->product?->descripcion ?? 'Material sin descripcion'); ?></p>
                                <span class="dw-pill <?php echo e($item->status === 'aprobado' ? 'approved' : ($item->status === 'rechazado' ? 'rejected' : 'pending')); ?>">
                                    <?php echo e(strtoupper((string) $item->status)); ?>

                                </span>
                            </div>
                            <p class="dw-side-user"><?php echo e($item->user?->name ?? 'Sin solicitante'); ?> - <?php echo e($item->destination); ?></p>
                            <div class="dw-side-meta">
                                <span class="dw-pill qty">Cant: <?php echo e($item->quantity); ?></span>
                                <span class="dw-pill time"><?php echo e(optional($item->requested_at)->format('H:i:s')); ?></span>
                            </div>
                        </article>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </aside>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showPasswordModal): ?>
        <div class="dw-modal-backdrop" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processElementKey('dw-password-modal', get_defined_vars()); ?>wire:key="dw-password-modal" wire:click.self="closePasswordModal" wire:keydown.escape="closePasswordModal">
            <div class="dw-modal">
                <div class="dw-modal-head">
                    <span class="dw-modal-kicker">Confirmacion final</span>
                    <h2 class="dw-modal-title">Ingresa la contraseña de retiro</h2>
                    <p class="dw-modal-text">Escribe la clave rapida del solicitante para terminar de registrar la hoja de recepcion de materiales diarios.</p>
                </div>

                <div class="dw-modal-body">
                    <label for="withdrawalPasswordModal" class="dw-label">Contraseña de retiro</label>
                    <div class="dw-input-wrap">
                        <input
                            id="withdrawalPasswordModal"
                            type="password"
                            wire:model.live="withdrawalPassword"
                            wire:keydown.enter.prevent="register"
                            maxlength="6"
                            class="dw-input has-clear"
                            placeholder="4 a 6 digitos"
                            autocomplete="one-time-code"
                        >
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($withdrawalPassword !== ''): ?>
                            <button type="button" class="dw-clear-btn" wire:click="clearField('withdrawalPassword')" aria-label="Limpiar contraseña">X</button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['withdrawalPassword'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="dw-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="dw-modal-actions">
                        <button type="button" class="dw-btn-secondary" wire:click="closePasswordModal">Cancelar</button>
                        <button type="button" class="dw-submit" wire:click="register">Registrar</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <script>
        if (!window.__kioskSuccessBound) {
            window.__kioskSuccessBound = true;

            const focusField = function (fieldId) {
                if (!fieldId) {
                    return;
                }

                setTimeout(function () {
                    const input = document.getElementById(fieldId);

                    if (!input || input.disabled) {
                        return;
                    }

                    input.focus({ preventScroll: true });
                    if (typeof input.select === 'function') {
                        input.select();
                    }
                }, 60);
            };

            focusField('productSearch');

            window.addEventListener('kiosk-focus-field', function (event) {
                focusField(event.detail?.field);
            });

            window.addEventListener('withdrawal-sent', function (event) {
                const box = document.getElementById('kiosk-success');

                if (!box) {
                    return;
                }

                if (window.__kioskSuccessTimeout) {
                    clearTimeout(window.__kioskSuccessTimeout);
                }

                box.textContent = event.detail.message ?? 'Solicitud Enviada';
                box.classList.remove('hidden');

                window.__kioskSuccessTimeout = setTimeout(function () {
                    box.classList.add('hidden');
                    window.__kioskSuccessTimeout = null;
                }, 5000);
            });
        }
    </script>
</section>
<?php /**PATH C:\laragon\www\SISTEMA-OFICIAL-AGARCORP\resources\views/livewire/daily-withdrawal-kiosk.blade.php ENDPATH**/ ?>