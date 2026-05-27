<x-filament-widgets::widget>
    <div class="ag-desktop-shell">
        <section class="ag-desktop-hero">
            <div class="ag-desktop-hero__copy">
                <p class="ag-desktop-kicker">Escritorio principal</p>
                <h1 class="ag-desktop-title">{{ $greeting }}, {{ $userName }}</h1>
                <p class="ag-desktop-subtitle">
                    Revisa tu perfil operativo y usa el menu lateral para entrar rapido a tus modulos.
                </p>

                <div class="ag-desktop-tags">
                    <span>{{ $today }}</span>
                    <span>
                        @if ($bcvRateValue)
                            Tasa BCV del dia ({{ $bcvRateValue }})
                        @else
                            Tasa BCV del dia (sin tasa cargada)
                        @endif
                    </span>
                </div>
            </div>

            <div class="ag-desktop-hero__panel">
                <div class="ag-desktop-panel-card">
                    <div class="ag-desktop-panel-label">Tu perfil operativo</div>
                    <div class="ag-desktop-panel-value">{{ $department }}</div>
                    <p class="ag-desktop-panel-text">
                        {{ $cargo }}
                    </p>

                    @if (!empty($lastVisitedModule['url']))
                        <div class="ag-desktop-panel-module">
                            <div class="ag-desktop-panel-module__eyebrow">Ultimo modulo abierto</div>
                            <div class="ag-desktop-panel-module__title">{{ $lastVisitedModule['title'] ?? 'Modulo reciente' }}</div>
                            <a class="ag-desktop-panel-module__link" href="{{ $lastVisitedModule['url'] }}">
                                Abrir de nuevo
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </section>

    </div>
</x-filament-widgets::widget>
