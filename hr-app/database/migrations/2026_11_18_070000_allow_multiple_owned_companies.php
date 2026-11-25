<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', fn (Blueprint $table) => $table->index('owner_id'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropUnique(['owner_id']));
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $table) => $table->unique('owner_id'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropIndex(['owner_id']));
    }
};
