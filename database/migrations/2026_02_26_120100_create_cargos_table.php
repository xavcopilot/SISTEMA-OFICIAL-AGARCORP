<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cargos')) {
            return;
        }

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
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};
