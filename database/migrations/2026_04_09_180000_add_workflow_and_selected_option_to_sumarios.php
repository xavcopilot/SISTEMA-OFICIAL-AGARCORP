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
        Schema::table('sumarios', function (Blueprint $table) {
            $table->string('workflow_estado', 50)->default('BORRADOR')->after('estado');

            $table->timestamp('enviado_validacion_finanzas_at')->nullable()->after('workflow_estado');
            $table->foreignId('enviado_por_user_id')->nullable()->after('enviado_validacion_finanzas_at')->constrained('users')->nullOnDelete();

            $table->timestamp('validado_finanzas_at')->nullable()->after('enviado_por_user_id');
            $table->foreignId('validado_por_user_id')->nullable()->after('validado_finanzas_at')->constrained('users')->nullOnDelete();
            $table->string('validacion_finanzas_resultado', 30)->nullable()->after('validado_por_user_id');
            $table->text('validacion_finanzas_comentario')->nullable()->after('validacion_finanzas_resultado');

            $table->timestamp('decision_gerencia_finanzas_at')->nullable()->after('validacion_finanzas_comentario');
            $table->foreignId('decision_gerencia_por_user_id')->nullable()->after('decision_gerencia_finanzas_at')->constrained('users')->nullOnDelete();
            $table->string('decision_gerencia_resultado', 30)->nullable()->after('decision_gerencia_por_user_id');
            $table->text('decision_gerencia_comentario')->nullable()->after('decision_gerencia_resultado');
        });

        Schema::table('sumario_item_opciones', function (Blueprint $table) {
            $table->boolean('seleccionada')->default(false)->after('precio_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sumario_item_opciones', function (Blueprint $table) {
            $table->dropColumn('seleccionada');
        });

        Schema::table('sumarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enviado_por_user_id');
            $table->dropConstrainedForeignId('validado_por_user_id');
            $table->dropConstrainedForeignId('decision_gerencia_por_user_id');

            $table->dropColumn([
                'workflow_estado',
                'enviado_validacion_finanzas_at',
                'validado_finanzas_at',
                'validacion_finanzas_resultado',
                'validacion_finanzas_comentario',
                'decision_gerencia_finanzas_at',
                'decision_gerencia_resultado',
                'decision_gerencia_comentario',
            ]);
        });
    }
};
