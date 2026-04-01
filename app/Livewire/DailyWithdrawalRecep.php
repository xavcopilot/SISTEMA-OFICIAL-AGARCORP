<?php

namespace App\Livewire;

use App\Models\DailyWithdrawal;
use App\Models\Product;
use App\Models\User;
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

    public string $quantity = '';

    public string $destination = '';

    public bool $requires_return = false;

    public ?string $return_date = null;

    public function updatedProductSearch(): void
    {
        $this->showProductSuggestions = true;

        if ($this->selectedProductLabel !== $this->productSearch) {
            $this->product_id = null;
        }
    }

    public function updatedUserSearch(): void
    {
        $this->showUserSuggestions = true;

        if ($this->selectedUserLabel !== $this->userSearch) {
            $this->user_id = null;
            $this->withdrawalPassword = '';
            $this->destination = '';
        }
    }

    public function updatedRequiresReturn(bool $value): void
    {
        if (! $value) {
            $this->return_date = null;
        }
    }

    public function openUserSuggestions(): void
    {
        $this->showUserSuggestions = true;
    }

    public function openProductSuggestions(): void
    {
        $this->showProductSuggestions = true;
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
    }

    public function register(): void
    {
        $validated = $this->validate([
            'product_id' => ['required', 'exists:products,id'],
            'user_id' => ['required', 'exists:users,id'],
            'withdrawalPassword' => ['required', 'string', 'regex:/^\d{4,6}$/'],
            'quantity' => ['required', 'integer', 'min:1'],
            'destination' => ['required', 'string', 'max:255'],
            'requires_return' => ['boolean'],
            'return_date' => ['nullable', 'required_if:requires_return,true', 'date', 'after_or_equal:today'],
        ], [
            'product_id.required' => 'Selecciona un material valido.',
            'user_id.required' => 'Selecciona un solicitante valido.',
            'withdrawalPassword.required' => 'Ingresa la contrasena de retiro.',
            'quantity.integer' => 'La cantidad debe ser un numero natural sin decimales.',
            'quantity.min' => 'La cantidad minima es 1.',
            'return_date.required_if' => 'Debes indicar la fecha de retorno.',
            'return_date.after_or_equal' => 'La fecha de retorno no puede ser anterior a hoy.',
        ]);

        $user = User::query()->find($validated['user_id']);

        // Solo se valida la clave rapida del solicitante; no se autentica ni cierra sesion del usuario de recepcion.
        if (! $user || blank($user->withdrawal_password) || ! Hash::check($validated['withdrawalPassword'], $user->withdrawal_password)) {
            $this->addError('withdrawalPassword', 'Contrasena de retiro incorrecta.');

            return;
        }

        DailyWithdrawal::query()->create([
            'user_id' => $validated['user_id'],
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'],
            'destination' => trim($validated['destination']),
            'requires_return' => (bool) $validated['requires_return'],
            'return_date' => (bool) $validated['requires_return'] ? $validated['return_date'] : null,
            'status' => 'pendiente',
            'warehouse_user_id' => null,
            'requested_at' => now(),
        ]);

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
            'destination',
            'requires_return',
            'return_date',
        ]);

        $this->dispatch('withdrawal-sent', message: 'Solicitud Enviada');
    }

    public function getProductSuggestionsProperty()
    {
        if (! $this->showProductSuggestions) {
            return collect();
        }

        $term = trim($this->productSearch);

        return Product::query()
            ->where('stock_actual', '>', 0)
            ->when($term !== '', function ($query) use ($term): void {
                $termLike = '%' . mb_strtolower($term) . '%';

                $query->where(function ($subQuery) use ($termLike): void {
                    $subQuery->whereRaw('LOWER(sku) LIKE ?', [$termLike])
                        ->orWhereRaw('LOWER(descripcion) LIKE ?', [$termLike]);
                });
            })
            ->orderBy('descripcion')
            ->limit(8)
            ->get(['id', 'sku', 'descripcion', 'stock_actual']);
    }

    public function getUserSuggestionsProperty()
    {
        if (! $this->showUserSuggestions) {
            return collect();
        }

        $term = trim($this->userSearch);

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
