<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profile_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('section', 30);
            $table->unsignedInteger('sequence');
            $table->text('payload');
            $table->unique(['employee_id', 'section', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profile_records');
    }
};
