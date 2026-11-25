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
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->index(['employee_id', 'starts_at']);
        });
        Schema::create('prior_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->date('month');
            $table->unsignedBigInteger('amount_cents');
            $table->unique(['employee_id', 'month']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prior_incomes');
        Schema::dropIfExists('work_shifts');
    }
};
