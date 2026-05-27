<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_withdrawal_request_id')->nullable()->constrained('daily_withdrawal_requests')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->string('destination');
            $table->boolean('requires_return')->default(false);
            $table->timestamp('return_date')->nullable();
            $table->enum('status', ['pendiente', 'aprobado', 'rechazado'])->default('pendiente');
            $table->string('rejection_reason', 255)->nullable();
            $table->foreignId('warehouse_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamps();

            $table->index('daily_withdrawal_request_id');
            $table->index('status');
            $table->index('requested_at');
            $table->index('user_id');
            $table->index('product_id');
            $table->index('warehouse_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_withdrawals');
    }
};
