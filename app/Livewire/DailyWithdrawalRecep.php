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
        $user = User::query()->find($userId);

        if (! $user) {
            return;
        }

        $this->user_id = $user->id;
        $this->selectedUserLabel = sprintf('%s (%s)', $user->name, $user->email);
        $this->userSearch = $this->selectedUserLabel;
        $this->showUserSuggestions = false;
    }

    public function register(): void
    {
        $validated = $this->validate([
            'product_id' => ['required', 'exists:products,id'],
            'user_id' => ['required', 'exists:users,id'],
            'withdrawalPassword' => ['required', 'string', 'regex:/^\d{4,6}$/'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'destination' => ['required', 'string', 'max:255'],
            'requires_return' => ['boolean'],
            'return_date' => ['nullable', 'required_if:requires_return,true', 'date', 'after_or_equal:today'],
        ], [
            'product_id.required' => 'Selecciona un material valido.',
            'user_id.required' => 'Selecciona un solicitante valido.',
            'withdrawalPassword.required' => 'Ingresa la contrasena de retiro.',
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

        if (mb_strlen(trim($this->productSearch)) < 2) {
            return collect();
        }

        return Product::query()
            ->where('stock_actual', '>', 0)
            ->where(function ($query): void {
                $query->where('sku', 'like', '%' . trim($this->productSearch) . '%')
                    ->orWhere('descripcion', 'like', '%' . trim($this->productSearch) . '%');
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
