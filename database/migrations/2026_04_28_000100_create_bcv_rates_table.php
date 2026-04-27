<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bcv_rates', function (Blueprint $table): void {
            $table->id();
            $table->date('rate_date')->unique();
            $table->decimal('rate', 12, 6);
            $table->string('source', 100);
            $table->string('source_url')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bcv_rates');
    }
};
