<?php if (! $__env->hasRenderedOnce('f61bbd05-3d39-437e-a8e1-6e37a157d6dc')): $__env->markAsRenderedOnce('f61bbd05-3d39-437e-a8e1-6e37a157d6dc'); ?>
    <style>
        #ag-bell-alert-toast {
            position: fixed;
            right: 1.25rem;
            bottom: 1.25rem;
            z-index: 9999;
            width: min(22rem, calc(100vw - 2rem));
            border: 1px solid rgba(245, 158, 11, 0.35);
            border-radius: 1rem;
            background: linear-gradient(135deg, rgba(255, 251, 235, 0.98), rgba(254, 243, 199, 0.98));
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.2);
            color: #78350f;
            padding: 0.95rem 1rem;
            opacity: 0;
            transform: translateY(1rem);
            pointer-events: none;
            transition: opacity 180ms ease, transform 180ms ease;
        }

        #ag-bell-alert-toast.ag-visible {
            opacity: 1;
            transform: translateY(0);
        }

        #ag-bell-alert-toast .ag-bell-alert-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #b45309;
        }

        #ag-bell-alert-toast .ag-bell-alert-message {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.94rem;
            font-weight: 600;
            line-height: 1.45;
        }
    </style>

    <script>
        (() => {
            const eventName = 'agarcorp-bell-alert';
            let toastTimer = null;
            let lastSignature = null;
            let lastPlayedAt = 0;

            const normalizePayload = (payload) => Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});

            const ensureToast = () => {
                let toast = document.getElementById('ag-bell-alert-toast');

                if (toast) {
                    return toast;
                }

                toast = document.createElement('div');
                toast.id = 'ag-bell-alert-toast';
                toast.setAttribute('role', 'status');
                toast.setAttribute('aria-live', 'polite');
                toast.innerHTML = '<span class="ag-bell-alert-label">Campanario AGARCORP</span><span class="ag-bell-alert-message"></span>';
                document.body.appendChild(toast);

                return toast;
            };

            const showToast = (message) => {
                const toast = ensureToast();
                const messageNode = toast.querySelector('.ag-bell-alert-message');

                if (! messageNode) {
                    return;
                }

                messageNode.textContent = message;
                toast.classList.add('ag-visible');

                if (toastTimer) {
                    window.clearTimeout(toastTimer);
                }

                toastTimer = window.setTimeout(() => {
                    toast.classList.remove('ag-visible');
                }, 4800);
            };

            const playTone = () => {
                const now = Date.now();

                if ((now - lastPlayedAt) < 2000) {
                    return;
                }

                lastPlayedAt = now;

                const AudioContextClass = window.AudioContext || window.webkitAudioContext;

                if (! AudioContextClass) {
                    return;
                }

                try {
                    const audioContext = new AudioContextClass();
                    const gain = audioContext.createGain();
                    gain.connect(audioContext.destination);
                    gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.08, audioContext.currentTime + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.48);

                    const oscillator = audioContext.createOscillator();
                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
                    oscillator.frequency.exponentialRampToValueAtTime(1174, audioContext.currentTime + 0.16);
                    oscillator.connect(gain);
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.5);

                    oscillator.onended = () => {
                        audioContext.close().catch(() => {});
                    };
                } catch (error) {
                    // El sonido es un extra; no debe romper la UI si el navegador lo bloquea.
                }
            };

            const speakMessage = (message) => {
                if (! ('speechSynthesis' in window) || ! message) {
                    return;
                }

                try {
                    window.speechSynthesis.cancel();
                    const utterance = new SpeechSynthesisUtterance(message);
                    utterance.lang = 'es-ES';
                    utterance.rate = 0.96;
                    utterance.pitch = 1;
                    window.speechSynthesis.speak(utterance);
                } catch (error) {
                    // No-op si el navegador restringe la voz.
                }
            };

            const handleAlert = (rawPayload) => {
                const payload = normalizePayload(rawPayload);
                const unreadCount = Number(payload.unreadCount ?? 0);
                const message = String(payload.message ?? '');
                const signature = unreadCount + '|' + message;

                if (unreadCount <= 1 || ! message || signature === lastSignature) {
                    return;
                }

                lastSignature = signature;

                showToast(message);
                playTone();
                speakMessage(message);
            };

            document.addEventListener('livewire:init', () => {
                if (typeof Livewire?.on === 'function') {
                    Livewire.on(eventName, handleAlert);
                }
            }, { once: true });
        })();
    </script>
<?php endif; ?><?php /**PATH C:\laragon\www\SISTEMA-OFICIAL-AGARCORP\resources\views/filament/notification-bell-alert.blade.php ENDPATH**/ ?>