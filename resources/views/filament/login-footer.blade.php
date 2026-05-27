<div class="ag-login-signature-row" aria-label="Firma corporativa y tema visual">
    <div class="ag-login-signature">
        Agarcorp de Venezuela C.A
    </div>

    <button
        type="button"
        id="ag-theme-toggle"
        class="ag-theme-toggle"
        aria-label="Cambiar modo claro u oscuro"
        title="Cambiar tema"
    >
        <span class="ag-theme-toggle-icon" aria-hidden="true">&#9790;</span>
    </button>
</div>

@once
    <script>
        (() => {
            const key = 'ag-theme-mode';
            const root = document.documentElement;

            const readStoredMode = () => {
                try {
                    const stored = localStorage.getItem(key);
                    if (stored === 'dark' || stored === 'light') {
                        return stored;
                    }
                } catch (error) {
                    // No-op when storage is blocked by browser settings.
                }

                return root.classList.contains('dark') ? 'dark' : 'light';
            };

            const persistMode = (mode) => {
                try {
                    localStorage.setItem(key, mode);
                } catch (error) {
                    // No-op when storage is blocked by browser settings.
                }
            };

            const applyMode = (mode, icon) => {
                root.classList.toggle('dark', mode === 'dark');
                icon.innerHTML = mode === 'dark' ? '&#9788;' : '&#9790;';
            };

            const initToggle = () => {
                const button = document.getElementById('ag-theme-toggle');
                if (! button) {
                    return;
                }

                const icon = button.querySelector('.ag-theme-toggle-icon');
                if (! icon) {
                    return;
                }

                let mode = readStoredMode();
                applyMode(mode, icon);

                button.addEventListener('click', () => {
                    mode = mode === 'dark' ? 'light' : 'dark';
                    applyMode(mode, icon);
                    persistMode(mode);
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initToggle, { once: true });
            } else {
                initToggle();
            }
        })();
    </script>
@endonce