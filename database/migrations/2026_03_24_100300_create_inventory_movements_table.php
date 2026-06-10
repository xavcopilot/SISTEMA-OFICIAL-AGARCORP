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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['ingreso', 'entrada', 'salida']);
            $table->string('nro_control')->nullable();
            $table->foreignId('almacenista_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('entregado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->string('orden_compra')->nullable();
            $table->string('nro_solicitud')->nullable();
            $table->string('factura_nota')->nullable();
            $table->string('nro_doc_legal')->nullable();
            $table->string('proveedor')->nullable();
            $table->string('entregado_por')->nullable();
            $table->string('almacenista')->nullable();
            $table->boolean('solicitar_formato_entrada')->default(false);
            $table->string('dpto_responsable')->nullable();
            $table->text('comentarios')->nullable();
            $table->unsignedInteger('total_items')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tipo');
            $table->index('fecha');
            $table->index('nro_control');
            $table->index(['tipo', 'solicitar_formato_entrada'], 'inv_mov_tipo_formato_idx');
            $table->index('almacenista_user_id');
            $table->index('entregado_por_user_id');
            $table->index('created_by_user_id');
            $table->index('updated_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
