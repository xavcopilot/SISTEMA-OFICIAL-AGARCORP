<x-filament-panels::page>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-200">
            Información Empresa > AGARCORP
        </h2>
    </x-slot>

    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Razon social</p>
                    <p class="mt-1 text-base text-gray-900 dark:text-gray-100">{{ $this->record->razon_social ?: '-' }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">RIF</p>
                    <p class="mt-1 text-base text-gray-900 dark:text-gray-100">{{ $this->record->rif ?: '-' }}</p>
                </div>

                <div class="md:col-span-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Direccion fiscal</p>
                    <p class="mt-1 text-base text-gray-900 dark:text-gray-100">{{ $this->record->direccion_fiscal ?: '-' }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Telefono principal</p>
                    <p class="mt-1 text-base text-gray-900 dark:text-gray-100">{{ $this->record->telefono_principal ?: '-' }}</p>
                </div>
            </div>

            <div class="mt-8">
                <a
                    href="{{ \App\Filament\Resources\InformacionAgarcorp\InformacionAgarcorpResource::getUrl('edit', ['record' => $this->record]) }}"
                    class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-500"
                >
                    Editar
                </a>
            </div>
        </section>
    </div>
</x-filament-panels::page>
