<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_claims', function (Blueprint $table) {
            $table->json('items')->nullable();
            $table->string('claim_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('expense_claims', fn (Blueprint $table) => $table->dropColumn(['items', 'claim_type']));
    }
};
