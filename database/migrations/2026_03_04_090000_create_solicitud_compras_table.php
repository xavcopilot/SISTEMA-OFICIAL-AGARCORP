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
        Schema::create('solicitud_compras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_control')->nullable();
            $table->unsignedBigInteger('numero_solicitud_usuario')->nullable()->index('solicitud_compras_numero_usuario_idx');
            $table->string('codigo_control_procura')->nullable();
            $table->date('fecha_solicitud')->nullable();

            $table->string('tipo_solicitud')->nullable();
            $table->string('prioridad')->nullable();

            $table->string('departamento_solicitante')->nullable();
            $table->text('para_ser_usado_en')->nullable();

            $table->string('centro')->nullable();
            $table->string('elemento')->nullable();
            $table->string('cuenta')->nullable();
            $table->string('contrato')->nullable();

            $table->foreignId('solicitado_por_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('por_almacen_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recibido_por_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('cargo_solicitante')->nullable();
            $table->string('cargo_almacen')->nullable();
            $table->string('cargo_aprobador')->nullable();
            $table->string('cargo_receptor')->nullable();

            $table->string('firma_solicitante')->nullable();
            $table->string('firma_almacen')->nullable();
            $table->string('firma_aprobador')->nullable();
            $table->string('firma_receptor')->nullable();

            $table->date('fecha_solicitante')->nullable();
            $table->date('fecha_almacen')->nullable();
            $table->date('fecha_aprobador')->nullable();
            $table->date('fecha_receptor')->nullable();
            $table->time('hora_receptor')->nullable();

            $table->string('rechazo_etapa')->nullable();
            $table->text('rechazo_comentario')->nullable();
            $table->foreignId('rechazo_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rechazo_destinatario_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rechazo_en')->nullable();

            $table->boolean('recepcion_conforme')->default(false);

            $table->string('estado')->default('BORRADOR');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_compras');
    }
};
