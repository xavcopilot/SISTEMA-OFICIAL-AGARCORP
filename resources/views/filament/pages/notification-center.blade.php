<x-filament-panels::page>
    <div class="grid gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <h2 class="text-lg font-semibold text-slate-900">Historial de Notificaciones</h2>
            <p class="mt-1 text-sm text-slate-600">
                Total: {{ $total }} | No leidas: {{ $unread }}
            </p>
            <p class="mt-2 text-sm text-slate-500">
                Puedes limpiar todo el historial con tu clave de inicio de sesion desde el boton superior.
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Ultimos eventos</h3>
            <div class="mt-3 grid gap-2">
                @forelse ($latest as $item)
                    @php($data = $item->data ?? [])
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <div class="text-sm font-semibold text-slate-900">{{ $data['title'] ?? 'Notificacion' }}</div>
                        @if(!empty($data['body']))
                            <div class="mt-1 text-sm text-slate-600">{{ $data['body'] }}</div>
                        @endif
                        <div class="mt-1 text-xs text-slate-500">{{ optional($item->created_at)->format('d/m/Y H:i') }}</div>
                    </div>
                @empty
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-600">
                        No tienes notificaciones registradas.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
