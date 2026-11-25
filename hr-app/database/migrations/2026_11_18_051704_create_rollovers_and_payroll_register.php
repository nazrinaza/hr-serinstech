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
        Schema::create('leave_rollovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->unsignedSmallInteger('closing_year');
            $table->unsignedInteger('closing_days');
            $table->unsignedInteger('carried_days');
            $table->unsignedInteger('new_allocation');
            $table->foreignId('processed_by')->constrained('users');
            $table->timestamps();
            $table->unique(['employee_id', 'closing_year']);
        });
        Schema::table('pay_periods', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable();
        });
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('pay_periods');
            $table->foreignId('employee_id')->constrained();
            $table->string('employee_name');
            $table->string('employee_code', 40);
            $table->string('company_name');
            foreach (['base', 'overtime', 'allowances', 'service_charge', 'deductions', 'gross', 'net'] as $field) {
                $table->unsignedBigInteger($field.'_cents');
            }
            $table->timestamps();
            $table->unique(['period_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
        Schema::table('pay_periods', fn (Blueprint $table) => $table->dropColumn('released_at'));
        Schema::dropIfExists('leave_rollovers');
    }
};
