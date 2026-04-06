<?php

namespace App\Livewire;

use App\Models\DailyWithdrawal;
use App\Models\DailyWithdrawalRequest;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Component;

class DailyWithdrawalRecep extends Component
{
    public string $productSearch = '';

    public ?int $product_id = null;

    public string $userSearch = '';

    public ?int $user_id = null;

    public bool $showProductSuggestions = false;

    public bool $showUserSuggestions = false;

    public string $withdrawalPassword = '';

    public ?string $selectedProductLabel = null;

    public ?string $selectedUserLabel = null;

    public string $quantity = '1';

    /**
     * @var array<int, array{product_id:int, sku:string, descripcion:string, quantity:int, requires_return:bool, return_date:?string}>
     */
    public array $items = [];

    public string $destination = '';

    public bool $requires_return = false;

    public ?string $return_date = null;

    private function getRequestedUnitsForProduct(int $productId, ?int $exceptItemIndex = null): int
    {
        return (int) collect($this->items)
            ->reject(fn (array $item, int $index): bool => $exceptItemIndex !== null && $index === $exceptItemIndex)
            ->filter(fn (array $item): bool => (int) ($item['product_id'] ?? 0) === $productId)
            ->sum(fn (array $item): int => (int) ($item['quantity'] ?? 0));
    }

    private function ensureStockAvailableForProduct(int $productId, int $requestedUnits, string $errorField = 'items'): bool
    {
        $product = Product::query()->find($productId);

        if (! $product) {
            $this->addError($errorField, 'Uno de los materiales seleccionados ya no existe en inventario.');

            return false;
        }

        $availableStock = (int) ($product->stock_actual ?? 0);

        if ($availableStock <= 0) {
            $this->addError($errorField, 'El material ' . $product->sku . ' - ' . $product->descripcion . ' ya no tiene stock disponible.');

            return false;
        }

        if ($requestedUnits > $availableStock) {
            $this->addError(
                $errorField,
                'Stock insuficiente para ' . $product->sku . ' - ' . $product->descripcion . '. Disponible: ' . $availableStock . ' / Solicitado: ' . $requestedUnits . '.'
            );

            return false;
        }

        return true;
    }

    public function updatedProductSearch(): void
    {
        $term = trim($this->productSearch);

        $this->showProductSuggestions = $term !== '';

        if ($this->selectedProductLabel !== $this->productSearch) {
            $this->product_id = null;
        }
    }

    public function updatedUserSearch(): void
    {
        $term = trim($this->userSearch);

        $this->showUserSuggestions = $term !== '';

        if ($this->selectedUserLabel !== $this->userSearch) {
            $this->user_id = null;
            $this->withdrawalPassword = '';
            $this->destination = '';
        }
    }

    public function openUserSuggestions(): void
    {
        $this->showUserSuggestions = trim($this->userSearch) !== '';
    }

    public function openProductSuggestions(): void
    {
        $this->showProductSuggestions = trim($this->productSearch) !== '';
    }

    public function closeProductSuggestions(): void
    {
        $this->showProductSuggestions = false;
    }

    public function closeUserSuggestions(): void
    {
        $this->showUserSuggestions = false;
    }

    public function clearField(string $field): void
    {
        if ($field === 'productSearch') {
            $this->productSearch = '';
            $this->product_id = null;
            $this->selectedProductLabel = null;
            $this->showProductSuggestions = true;

            return;
        }

        if ($field === 'userSearch') {
            $this->userSearch = '';
            $this->user_id = null;
            $this->selectedUserLabel = null;
            $this->withdrawalPassword = '';
            $this->destination = '';
            $this->showUserSuggestions = true;

            return;
        }

        if ($field === 'withdrawalPassword') {
            $this->withdrawalPassword = '';

            return;
        }

        if ($field === 'quantity') {
            $this->quantity = '';

            return;
        }

        if ($field === 'destination') {
            $this->destination = '';

            return;
        }

        if ($field === 'return_date') {
            $this->return_date = null;
        }
    }

    public function selectProduct(int $productId): void
    {
        $product = Product::query()->find($productId);

        if (! $product) {
            return;
        }

        $this->product_id = $product->id;
        $this->selectedProductLabel = sprintf('%s - %s', $product->sku, $product->descripcion);
        $this->productSearch = $this->selectedProductLabel;
        $this->showProductSuggestions = false;
        $this->dispatch('kiosk-focus-field', field: 'quantity');
    }

    public function selectSingleProductSuggestion(): void
    {
        $this->showProductSuggestions = true;

        $suggestions = $this->getProductSuggestionsProperty();

        if ($suggestions->count() !== 1) {
            return;
        }

        $this->selectProduct((int) $suggestions->first()->id);
    }

    public function addItem(): void
    {
        $this->resetErrorBag('product_id');
        $this->resetErrorBag('quantity');
        $this->resetErrorBag('items');

        $validated = $this->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'requires_return' => ['boolean'],
            'return_date' => ['nullable', 'required_if:requires_return,true', 'date', 'after_or_equal:today'],
        ], [
            'product_id.required' => 'Selecciona un material valido.',
            'quantity.required' => 'Indica la cantidad del material.',
            'quantity.integer' => 'La cantidad debe ser un numero natural sin decimales.',
            'quantity.min' => 'La cantidad minima es 1.',
            'return_date.required_if' => 'Debes indicar la fecha de retorno para este material.',
            'return_date.after_or_equal' => 'La fecha de retorno no puede ser anterior a hoy.',
        ]);

        $product = Product::query()->find((int) $validated['product_id']);

        if (! $product || (int) ($product->stock_actual ?? 0) <= 0) {
            $this->addError('product_id', 'El material no esta disponible para retiro.');

            return;
        }

        $quantity = (int) $validated['quantity'];
        $requestedUnits = $this->getRequestedUnitsForProduct((int) $product->id) + $quantity;

        if (! $this->ensureStockAvailableForProduct((int) $product->id, $requestedUnits, 'quantity')) {
            return;
        }

        $itemRequiresReturn = (bool) ($validated['requires_return'] ?? false);
        $itemReturnDate = $itemRequiresReturn ? (string) ($validated['return_date'] ?? '') : null;

        $existingIndex = collect($this->items)
            ->search(fn (array $item): bool =>
                (int) $item['product_id'] === (int) $product->id
                && (bool) $item['requires_return'] === $itemRequiresReturn
                && (($item['return_date'] ?? null) === $itemReturnDate)
            );

        if ($existingIndex !== false) {
            $this->items[$existingIndex]['quantity'] += $quantity;
        } else {
            $this->items[] = [
                'product_id' => (int) $product->id,
                'sku' => (string) $product->sku,
                'descripcion' => (string) $product->descripcion,
                'quantity' => $quantity,
                'requires_return' => $itemRequiresReturn,
                'return_date' => $itemReturnDate,
            ];
        }

        $this->clearField('productSearch');
        $this->clearField('quantity');
        $this->requires_return = false;
        $this->return_date = null;
        $this->quantity = '1';
        $this->dispatch('kiosk-focus-field', field: 'productSearch');
    }

    public function removeItem(int $index): void
    {
        if (! array_key_exists($index, $this->items)) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function selectUser(int $userId): void
    {
        $user = User::query()->with('departamento:id,nombre')->find($userId);

        if (! $user) {
            return;
        }

        $this->user_id = $user->id;
        $this->selectedUserLabel = sprintf('%s (%s)', $user->name, $user->email);
        $this->userSearch = $this->selectedUserLabel;
        $this->destination = (string) ($user->departamento?->nombre ?? '');
        $this->showUserSuggestions = false;
        $this->dispatch('kiosk-focus-field', field: 'withdrawalPassword');
    }

    public function register(): void
    {
        $this->resetErrorBag('items');

        $validated = $this->validate([
            'user_id' => ['required', 'exists:users,id'],
            'withdrawalPassword' => ['required', 'string', 'regex:/^\d{4,6}$/'],
            'destination' => ['required', 'string', 'max:255'],
        ], [
            'user_id.required' => 'Selecciona un solicitante valido.',
            'withdrawalPassword.required' => 'Ingresa la contrasena de retiro.',
        ]);

        if (count($this->items) === 0) {
            $this->addError('items', 'Agrega al menos un material para registrar el retiro.');

            return;
        }

        $user = User::query()->find($validated['user_id']);

        // Solo se valida la clave rapida del solicitante; no se autentica ni cierra sesion del usuario de recepcion.
        if (! $user || blank($user->withdrawal_password) || ! Hash::check($validated['withdrawalPassword'], $user->withdrawal_password)) {
            $this->addError('withdrawalPassword', 'Contrasena de retiro incorrecta.');

            return;
        }

        $requestedProducts = collect($this->items)
            ->groupBy(fn (array $item): int => (int) $item['product_id'])
            ->map(fn ($productItems): int => (int) collect($productItems)->sum(fn (array $item): int => (int) ($item['quantity'] ?? 0)));

        foreach ($requestedProducts as $productId => $requestedUnits) {
            if (! $this->ensureStockAvailableForProduct((int) $productId, (int) $requestedUnits, 'items')) {
                return;
            }
        }

        DB::transaction(function () use ($validated): void {
            $itemsCollection = collect($this->items);
            $hasAnyReturn = $itemsCollection->contains(fn (array $item): bool => (bool) ($item['requires_return'] ?? false));
            $returnDates = $itemsCollection
                ->filter(fn (array $item): bool => (bool) ($item['requires_return'] ?? false))
                ->pluck('return_date')
                ->filter()
                ->unique()
                ->values();
            $requestReturnDate = $returnDates->count() === 1 ? $returnDates->first() : null;

            $request = DailyWithdrawalRequest::query()->create([
                'user_id' => $validated['user_id'],
                'destination' => trim($validated['destination']),
                'requires_return' => $hasAnyReturn,
                'return_date' => $requestReturnDate,
                'status' => 'pendiente',
                'requested_at' => now(),
            ]);

            foreach ($this->items as $item) {
                $itemRequiresReturn = (bool) ($item['requires_return'] ?? false);
                $itemReturnDate = $itemRequiresReturn ? ($item['return_date'] ?? null) : null;

                DailyWithdrawal::query()->create([
                    'daily_withdrawal_request_id' => $request->id,
                    'user_id' => $validated['user_id'],
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'destination' => trim($validated['destination']),
                    'requires_return' => $itemRequiresReturn,
                    'return_date' => $itemReturnDate,
                    'status' => 'pendiente',
                    'warehouse_user_id' => null,
                    'requested_at' => now(),
                ]);
            }
        });

        $this->reset([
            'productSearch',
            'product_id',
            'userSearch',
            'user_id',
            'showUserSuggestions',
            'showProductSuggestions',
            'withdrawalPassword',
            'selectedProductLabel',
            'selectedUserLabel',
            'quantity',
            'items',
            'destination',
            'requires_return',
            'return_date',
        ]);

        $this->quantity = '1';

        $this->dispatch('withdrawal-sent', message: 'Solicitud Enviada');
        $this->dispatch('kiosk-focus-field', field: 'productSearch');
    }

    public function getProductSuggestionsProperty()
    {
        if (! $this->showProductSuggestions) {
            return collect();
        }

        $term = trim($this->productSearch);

        if ($term === '') {
            return collect();
        }

        $termLower = mb_strtolower($term);
        $termPrefix = $termLower . '%';
        $termContains = '%' . $termLower . '%';

        return Product::query()
            ->where('stock_actual', '>', 0)
            ->where(function ($subQuery) use ($termPrefix, $termContains): void {
                $subQuery->whereRaw('LOWER(sku) LIKE ?', [$termPrefix])
                    ->orWhereRaw('LOWER(descripcion) LIKE ?', [$termPrefix])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$termContains])
                    ->orWhereRaw('LOWER(descripcion) LIKE ?', [$termContains]);
            })
            ->orderByRaw(
                "CASE
                    WHEN LOWER(sku) = ? THEN 1
                    WHEN LOWER(sku) LIKE ? THEN 2
                    WHEN LOWER(descripcion) LIKE ? THEN 3
                    ELSE 4
                END",
                [$termLower, $termPrefix, $termPrefix]
            )
            ->orderBy('sku')
            ->limit(8)
            ->get(['id', 'sku', 'descripcion', 'stock_actual']);
    }

    public function getUserSuggestionsProperty()
    {
        if (! $this->showUserSuggestions) {
            return collect();
        }

        $term = trim($this->userSearch);

        if ($term === '') {
            return collect();
        }

        return User::query()
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($subQuery) use ($term): void {
                    $subQuery->where('name', 'like', '%' . $term . '%')
                        ->orWhere('email', 'like', '%' . $term . '%');
                });
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email']);
    }

    public function getLatestTodayWithdrawalsProperty()
    {
        return DailyWithdrawal::query()
            ->with(['user:id,name', 'product:id,descripcion'])
            ->whereDate('requested_at', now()->toDateString())
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.daily-withdrawal-kiosk')
            ->layout('layouts.recepcion', ['title' => 'Almacen (Retiros Diarios)']);
    }
}
