<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sumario_proveedor_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sumario_id')->constrained('sumarios')->cascadeOnDelete();
            $table->unsignedTinyInteger('opcion_numero');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('proveedor_nombre_snapshot');
            $table->string('archivo_path');
            $table->string('nombre_original')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->foreignId('subido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sumario_id', 'opcion_numero'], 'sumario_proveedor_documentos_sumario_slot_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sumario_proveedor_documentos');
    }
};