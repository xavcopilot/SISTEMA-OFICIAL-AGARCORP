<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('solicitud_compras', function (Blueprint $table) {
            $table->unsignedBigInteger('numero_solicitud_usuario')
                ->nullable()
                ->after('codigo_control')
                ->index('solicitud_compras_numero_usuario_idx');
        });

        $records = DB::table('solicitud_compras')
            ->select(['id', 'solicitado_por_user_id', 'codigo_control'])
            ->orderBy('solicitado_por_user_id')
            ->orderBy('id')
            ->get();

        $countersByUser = [];
        $sequenceByExpediente = [];

        foreach ($records as $record) {
            $requesterId = (int) ($record->solicitado_por_user_id ?? 0);

            if ($requesterId <= 0) {
                continue;
            }

            $sharedCode = trim((string) ($record->codigo_control ?: $record->id));
            $expedienteKey = $requesterId . '|' . $sharedCode;

            if (! isset($sequenceByExpediente[$expedienteKey])) {
                $countersByUser[$requesterId] = ($countersByUser[$requesterId] ?? 0) + 1;
                $sequenceByExpediente[$expedienteKey] = $countersByUser[$requesterId];
            }

            DB::table('solicitud_compras')
                ->where('id', $record->id)
                ->update([
                    'numero_solicitud_usuario' => $sequenceByExpediente[$expedienteKey],
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_compras', function (Blueprint $table) {
            $table->dropIndex('solicitud_compras_numero_usuario_idx');
            $table->dropColumn('numero_solicitud_usuario');
        });
    }
};
