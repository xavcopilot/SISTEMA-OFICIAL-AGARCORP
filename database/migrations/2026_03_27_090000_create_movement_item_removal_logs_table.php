<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movement_item_removal_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movement_id')->constrained('inventory_movements')->cascadeOnDelete();
            $table->foreignId('movement_item_id')->nullable()->constrained('movement_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('sku_snapshot', 80)->nullable();
            $table->unsignedInteger('cantidad')->default(0);
            $table->string('motivo', 50);
            $table->foreignId('removed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['movement_id', 'created_at']);
            $table->index('motivo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_item_removal_logs');
    }
};
