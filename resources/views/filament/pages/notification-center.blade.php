<x-filament-panels::page>
    <div class="grid gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <h2 class="text-lg font-semibold text-slate-900">Modulos con Notificaciones</h2>
            <p class="mt-1 text-sm text-slate-600">
                Modulos con notificaciones: {{ $notificationModules }} | Con pendientes visibles: {{ $activeModules }}
            </p>
            <p class="mt-2 text-sm text-slate-500">
                Aqui aparecen los modulos que usan este esquema de notificaciones. Si un modulo esta en 0, sigue listado pero sin pendientes visibles por atender.
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Bandeja por modulo</h3>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($modules as $module)
                    <a
                        href="{{ $module['url'] }}"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-primary-300 hover:bg-white"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ $module['group'] }}</div>
                                <div class="mt-1 text-base font-semibold text-slate-900">{{ $module['label'] }}</div>
                            </div>
                            <span @class([
                                'inline-flex min-w-10 items-center justify-center rounded-full px-3 py-1 text-sm font-semibold',
                                'bg-slate-200 text-slate-700' => $module['badgeColor'] === 'gray',
                                'bg-amber-100 text-amber-700' => $module['badgeColor'] === 'warning',
                                'bg-rose-100 text-rose-700' => $module['badgeColor'] === 'danger',
                                'bg-emerald-100 text-emerald-700' => $module['badgeColor'] === 'success',
                                'bg-sky-100 text-sky-700' => ! in_array($module['badgeColor'], ['gray', 'warning', 'danger', 'success'], true),
                            ])>
                                {{ $module['count'] }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">{{ $module['description'] }}</p>
                        <p class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                            {{ $module['hasPending'] ? 'Con pendientes visibles' : 'Sin pendientes visibles' }}
                        </p>
                    </a>
                @empty
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-600 md:col-span-2 xl:col-span-3">
                        No hay modulos configurados para este esquema de notificaciones en tu perfil actual.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Ultimos eventos</h3>
            <p class="mt-1 text-sm text-slate-600">
                Historial total: {{ $total }} | No leidas: {{ $unread }}
            </p>
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
            <p class="mt-3 text-sm text-slate-500">
                Puedes limpiar todo el historial con tu clave de inicio de sesion desde el boton superior.
            </p>
        </div>
    </div>
</x-filament-panels::page>
