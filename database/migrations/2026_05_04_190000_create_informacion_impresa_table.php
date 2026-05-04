<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informacion_impresa', function (Blueprint $table): void {
            $table->id();
            $table->string('razon_social', 255)->default('AGARCORP');
            $table->string('rif', 60)->nullable();
            $table->string('direccion_fiscal', 500)->nullable();
            $table->string('telefono_principal', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informacion_impresa');
    }
};
