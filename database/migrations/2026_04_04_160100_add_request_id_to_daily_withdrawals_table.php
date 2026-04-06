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
        Schema::table('daily_withdrawals', function (Blueprint $table) {
            $table->foreignId('daily_withdrawal_request_id')
                ->nullable()
                ->after('id')
                ->constrained('daily_withdrawal_requests')
                ->nullOnDelete();

            $table->index('daily_withdrawal_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_withdrawals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('daily_withdrawal_request_id');
        });
    }
};
