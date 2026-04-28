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
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->string('rechazo_etapa')->nullable()->after('factura_procesada_administracion_at');
            $table->text('rechazo_comentario')->nullable()->after('rechazo_etapa');
            $table->foreignId('rechazo_por_user_id')->nullable()->after('rechazo_comentario')->constrained('users')->nullOnDelete();
            $table->timestamp('rechazo_en')->nullable()->after('rechazo_por_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rechazo_por_user_id');
            $table->dropColumn(['rechazo_etapa', 'rechazo_comentario', 'rechazo_en']);
        });
    }
};
