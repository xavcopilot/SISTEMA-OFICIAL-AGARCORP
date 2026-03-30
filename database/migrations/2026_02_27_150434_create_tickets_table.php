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
    Schema::create('tickets', function (Blueprint $table) {
        $table->id();
        // Relación con el usuario que crea el ticket
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        
        // Datos básicos (Sección 1 y 2)
        $table->string('nombre_solicitante'); // Nombre y Apellido
        $table->string('departamento');       // El de la lista de 11 opciones
        $table->enum('tipo_solicitud', ['SOPORTE_IT', 'CAMBIO_TONER']);
        
        // Campos específicos para Soporte IT (Sección 3)
        $table->string('nivel_urgencia')->nullable(); // Alta, Media, Baja
        $table->string('equipo_afectado')->nullable();
        $table->text('descripcion_problema')->nullable();
        
        // Campos específicos para Cambio de Tóner (Sección 4)
        $table->string('codigo_impresora')->nullable(); // Los códigos ADV-HP...
        $table->string('color_toner')->nullable();     // Negro, Cyan, Yellow, Magenta
        
        // Gestión de A.I.T
        $table->enum('estado', ['Abierto', 'En Proceso', 'Resuelto', 'Cancelado'])->default('Abierto');
        $table->timestamps();

        $table->index(['estado', 'created_at'], 'tickets_estado_created_at_index');
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
