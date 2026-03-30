<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
        });

        DB::table('cargos')->insert([
            ['nombre' => 'Analista'],
            ['nombre' => 'Lider'],
            ['nombre' => 'Tecnico'],
            ['nombre' => 'Coordinador'],
            ['nombre' => 'Vicepresidente'],
            ['nombre' => 'Gerente'],
        ]);

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cargo_id')) {
                $table->foreignId('cargo_id')->nullable()->after('departamento_id')->constrained('cargos')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'cargo_id')) {
                $table->dropForeign(['cargo_id']);
                $table->dropColumn('cargo_id');
            }
        });

        Schema::dropIfExists('cargos');
    }
};
