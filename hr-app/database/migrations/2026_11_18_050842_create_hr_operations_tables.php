<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('title');
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('expense_claims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->date('spent_on');
            $t->string('description');
            $t->decimal('amount', 12, 2);
            $t->string('receipt')->nullable();
            $t->string('status')->default('pending');
            $t->timestamps();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable();
        });
        Schema::create('attendance_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->string('status')->default('open');
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable();
            $t->index(['employee_id', 'ended_at']);
        });
        Schema::create('appraisal_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('title');
            $t->text('prompt');
            $t->timestamps();
        });
        Schema::create('appraisals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('template_id')->constrained('appraisal_templates');
            $t->date('due_on');
            $t->text('response')->nullable();
            $t->text('feedback')->nullable();
            $t->timestamps();
        });
        Schema::create('pay_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->timestamps();
            $t->unique(['company_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        foreach (['pay_periods', 'appraisals', 'appraisal_templates', 'attendance_entries', 'expense_claims', 'company_posts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
