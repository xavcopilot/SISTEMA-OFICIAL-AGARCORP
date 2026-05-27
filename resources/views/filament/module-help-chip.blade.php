@php
    $title = (string) ($help['title'] ?? 'Ayuda del modulo');
    $description = (string) ($help['description'] ?? '');
    $windows = is_array($help['windows'] ?? null) ? $help['windows'] : [];
@endphp

<div x-data="{ open: false }" class="ag-module-help-wrap">
    <button
        type="button"
        @click="open = true"
        class="ag-module-help-button"
        title="Ayuda del modulo"
        aria-label="Ayuda del modulo"
    >
        !
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="ag-module-help-overlay"
        @click="open = false"
    ></div>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="ag-module-help-modal"
        role="dialog"
        aria-modal="true"
        aria-label="Ayuda del modulo"
    >
        <div class="ag-module-help-head">
            <div>
                <div class="ag-module-help-title">{{ $title }}</div>
                <div class="ag-module-help-desc">{{ $description }}</div>
            </div>
            <button type="button" class="ag-module-help-close" @click="open = false">Cerrar</button>
        </div>

        <div class="ag-module-help-body">
            @if ($windows === [])
                <div class="ag-module-help-empty">Este modulo no tiene ventanas adicionales configuradas.</div>
            @else
                <div class="ag-module-help-grid">
                    @foreach ($windows as $window)
                        <article class="ag-module-help-card">
                            <h4>{{ (string) ($window['title'] ?? 'Ventana') }}</h4>
                            <p>{{ (string) ($window['description'] ?? '') }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    .ag-module-help-wrap {
        display: inline-flex;
        align-items: center;
        margin-inline-end: 0.65rem;
    }

    .ag-module-help-button {
        width: 1.95rem;
        height: 1.95rem;
        border-radius: 999px;
        border: 1px solid #d1d5db;
        background: #fff;
        color: #0f172a;
        font-weight: 800;
        font-size: 0.95rem;
        line-height: 1;
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
        transition: all .18s ease;
    }

    .ag-module-help-button:hover {
        transform: translateY(-1px);
        border-color: #94a3b8;
        box-shadow: 0 5px 12px rgba(15, 23, 42, 0.16);
    }

    .ag-module-help-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        z-index: 80;
    }

    .ag-module-help-modal {
        position: fixed;
        z-index: 81;
        inset-inline: 50%;
        top: 8vh;
        transform: translateX(-50%);
        width: min(880px, calc(100vw - 2rem));
        max-height: 84vh;
        overflow: auto;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 28px 80px rgba(15, 23, 42, 0.26);
    }

    .ag-module-help-head {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: start;
        padding: 1rem 1rem 0.75rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .ag-module-help-title {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
    }

    .ag-module-help-desc {
        margin-top: 0.3rem;
        font-size: 0.9rem;
        color: #475569;
    }

    .ag-module-help-close {
        border: 1px solid #d1d5db;
        background: #f8fafc;
        color: #1e293b;
        border-radius: 8px;
        font-size: 0.82rem;
        padding: 0.4rem 0.6rem;
        cursor: pointer;
    }

    .ag-module-help-body {
        padding: 0.95rem 1rem 1rem;
    }

    .ag-module-help-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 0.75rem;
    }

    .ag-module-help-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        padding: 0.72rem;
    }

    .ag-module-help-card h4 {
        margin: 0;
        font-size: 0.86rem;
        font-weight: 800;
        color: #0f172a;
    }

    .ag-module-help-card p {
        margin: 0.45rem 0 0;
        font-size: 0.8rem;
        color: #475569;
        line-height: 1.3;
    }

    .ag-module-help-empty {
        font-size: 0.86rem;
        color: #475569;
        padding: 0.5rem 0;
    }
</style>
