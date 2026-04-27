<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table): void {
            $table->string('sitio_entrega')->nullable()->after('departamento_solicitante');
            $table->text('comentarios')->nullable()->after('sitio_entrega');

            $table->foreignId('elaborado_por_user_id')->nullable()->after('comentarios')->constrained('users')->nullOnDelete();
            $table->timestamp('elaborado_firmado_at')->nullable()->after('elaborado_por_user_id');

            $table->foreignId('aprobado_por_user_id')->nullable()->after('elaborado_firmado_at')->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_firmado_at')->nullable()->after('aprobado_por_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('aprobado_por_user_id');
            $table->dropColumn('aprobado_firmado_at');

            $table->dropConstrainedForeignId('elaborado_por_user_id');
            $table->dropColumn('elaborado_firmado_at');

            $table->dropColumn('comentarios');
            $table->dropColumn('sitio_entrega');
        });
    }
};
