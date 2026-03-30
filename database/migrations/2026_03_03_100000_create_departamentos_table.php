<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('departamento_id')->nullable()->after('email')->constrained('departamentos')->nullOnDelete();
            // drop the old string column if exists
            if (Schema::hasColumn('users', 'departamento')) {
                $table->dropColumn('departamento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'departamento_id')) {
                $table->dropForeign(['departamento_id']);
                $table->dropColumn('departamento_id');
            }
            // could recreate string column but skip for simplicity
        });

        Schema::dropIfExists('departamentos');
    }
};