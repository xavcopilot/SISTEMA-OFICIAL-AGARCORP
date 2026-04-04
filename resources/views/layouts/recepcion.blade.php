<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Recepcion AGARCORP' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>
            :root {
                --k-screen-margin: 2cm;
                --k-bg-1: #f3eee0;
                --k-bg-2: #dde9f5;
                --k-ink: #122035;
                --k-muted: #506580;
                --k-card: rgba(255, 255, 255, 0.86);
                --k-border: rgba(20, 35, 53, 0.2);
                --k-primary: #1f5bd8;
                --k-primary-2: #194ab0;
                --k-success-bg: #ecfdf3;
                --k-success-border: #8be7b0;
                --k-success-ink: #0f7a41;
            }

            @media (min-width: 1500px) {
                :root {
                    --k-screen-margin: 2cm;
                }
            }

            @media (min-width: 1800px) {
                :root {
                    --k-screen-margin: 2cm;
                }
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                color: var(--k-ink);
                font-family: "Segoe UI", "Inter", Tahoma, Geneva, Verdana, sans-serif;
            }

            .kiosk-bg {
                background:
                    radial-gradient(circle at 10% 8%, rgba(247, 176, 67, 0.24), transparent 40%),
                    radial-gradient(circle at 88% 2%, rgba(50, 109, 182, 0.2), transparent 35%),
                    linear-gradient(150deg, var(--k-bg-1) 0%, var(--k-bg-2) 55%, #f5faff 100%);
            }

            .kiosk-shell {
                width: calc(100vw - (var(--k-screen-margin) * 2));
                max-width: none;
                margin: 0 auto;
                padding: 0;
                min-height: calc(100vh - (var(--k-screen-margin) * 2));
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
            }

            .kiosk-topbar {
                display: grid;
                grid-template-columns: 1fr auto 1fr;
                align-items: center;
                gap: 12px;
                margin-bottom: 14px;
            }

            .kiosk-clock {
                border: 1px solid var(--k-border);
                background: var(--k-card);
                border-radius: 14px;
                padding: 10px 14px;
                box-shadow: 0 10px 30px rgba(15, 36, 60, 0.09);
                min-width: 135px;
                justify-self: center;
            }

            .kiosk-clock-label {
                margin: 0;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.12em;
                color: var(--k-muted);
                font-weight: 700;
            }

            .kiosk-clock-value {
                margin: 2px 0 0;
                font-size: 26px;
                font-weight: 800;
                line-height: 1;
            }

            .kiosk-brand {
                display: flex;
                align-items: center;
                justify-content: center;
                justify-self: start;
            }

            .kiosk-brand img {
                display: block;
                width: auto;
                height: 98px;
            }

            @media (min-width: 1500px) {
                .kiosk-brand img {
                    height: 110px;
                }

                .kiosk-clock {
                    padding: 13px 18px;
                    border-radius: 16px;
                    min-width: 178px;
                }

                .kiosk-clock-label {
                    font-size: 12px;
                }

                .kiosk-clock-value {
                    font-size: 34px;
                }
            }

            @media (min-width: 1800px) {
                .kiosk-clock {
                    padding: 15px 22px;
                    min-width: 198px;
                }

                .kiosk-clock-value {
                    font-size: 38px;
                }
            }

            .kiosk-logout-btn {
                border: 0;
                border-radius: 12px;
                padding: 10px 16px;
                color: #fff;
                background: linear-gradient(135deg, #ce3d3d, #a52525);
                font-weight: 700;
                font-size: 13px;
                letter-spacing: 0.04em;
                cursor: pointer;
                box-shadow: 0 8px 20px rgba(130, 22, 22, 0.25);
            }

            .kiosk-history-btn {
                border: 1px solid var(--k-border);
                border-radius: 12px;
                padding: 10px 16px;
                color: var(--k-ink);
                background: var(--k-card);
                font-weight: 700;
                font-size: 13px;
                letter-spacing: 0.03em;
                cursor: pointer;
                box-shadow: 0 8px 20px rgba(20, 35, 53, 0.15);
            }

            .kiosk-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                justify-self: end;
            }

            .kiosk-main {
                margin-top: 8px;
            }

            .kiosk-success {
                border: 1px solid var(--k-success-border);
                background: var(--k-success-bg);
                color: var(--k-success-ink);
                border-radius: 12px;
                padding: 10px 14px;
                margin-bottom: 12px;
                font-weight: 600;
            }

            @media (max-width: 900px) {
                :root {
                    --k-screen-margin: 12px;
                }

                .kiosk-shell {
                    width: calc(100vw - (var(--k-screen-margin) * 2));
                    min-height: calc(100vh - (var(--k-screen-margin) * 2));
                    margin: var(--k-screen-margin) auto;
                    padding: 0;
                    justify-content: flex-start;
                }

                .kiosk-topbar {
                    flex-wrap: wrap;
                    justify-content: center;
                }

                .kiosk-brand {
                    order: -1;
                }

                .kiosk-brand img {
                    height: 82px;
                }
            }
        </style>
    </head>
    <body class="kiosk-bg min-h-screen text-slate-800 antialiased">
        <div class="kiosk-shell" style="margin-top: var(--k-screen-margin); margin-bottom: var(--k-screen-margin);">
            <div class="kiosk-topbar">
                <div class="kiosk-brand">
                    <img src="{{ asset('images/logo-agarcorp.png') }}" alt="AGARCORP">
                </div>

                <div class="kiosk-clock">
                    <p class="kiosk-clock-label">Hora</p>
                    <p id="kiosk-clock" class="kiosk-clock-value">--:--:--</p>
                </div>

                <div class="kiosk-actions">
                    <button type="button" id="kiosk-history-toggle" class="kiosk-history-btn">Ultimos envios</button>

                    <form method="POST" action="{{ route('recepcion.logout') }}">
                        @csrf
                        <button type="submit" class="kiosk-logout-btn">Cerrar Sesion</button>
                    </form>
                </div>
            </div>

            <main class="kiosk-main">
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
        <script>
            (function () {
                const target = document.getElementById('kiosk-clock');

                if (!target) {
                    return;
                }

                const updateClock = () => {
                    const now = new Date();
                    target.textContent = now.toLocaleTimeString('es-VE', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                    });
                };

                updateClock();
                setInterval(updateClock, 1000);
            })();

            (function () {
                const button = document.getElementById('kiosk-history-toggle');
                const panel = document.getElementById('kiosk-history-panel');

                if (!button || !panel) {
                    return;
                }

                const closePanel = () => {
                    panel.classList.add('hidden');
                };

                button.addEventListener('click', function () {
                    panel.classList.toggle('hidden');
                });

                document.addEventListener('click', function (event) {
                    if (panel.classList.contains('hidden')) {
                        return;
                    }

                    if (panel.contains(event.target) || button.contains(event.target)) {
                        return;
                    }

                    closePanel();
                });
            })();
        </script>
    </body>
</html>
