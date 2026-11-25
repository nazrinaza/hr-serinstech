<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class YearEndPayrollTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $company = DB::table('companies')->insertGetId(['name' => 'Sample', 'owner_id' => $owner->id]);
        $person = DB::table('employees')->insertGetId(['company_id' => $company, 'user_id' => $staff->id, 'name' => 'Sample Employee', 'code' => 'E01', 'leave_balance' => 6, 'leave_allocation' => 12]);

        return [$owner, $staff, $company, $person];
    }

    public function test_capped_rollover_preserves_future_leave_and_cannot_run_twice(): void
    {
        [$owner,$staff,,$person] = $this->workspace();
        $year = now('Asia/Kuala_Lumpur')->year - 1;
        DB::table('leave_requests')->insert(['employee_id' => $person, 'status' => 'approved', 'days' => 2, 'start_date' => ($year + 1).'-03-01', 'end_date' => ($year + 1).'-03-02']);
        $data = ['year' => $year, 'carry_limit' => 3, 'allocation' => 12];
        $url = "/employees/$person/rollover";
        $this->actingAs($staff)->post($url, $data)->assertForbidden();
        $this->actingAs($owner)->post($url, $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_rollovers', ['employee_id' => $person, 'closing_days' => 8, 'carried_days' => 3]);
        $this->assertDatabaseHas('employees', ['id' => $person, 'leave_balance' => 13]);
        $this->post($url, $data)->assertSessionHasErrors('year');
        $this->actingAs($staff)->post('/leave', ['start_date' => "$year-03-03", 'end_date' => "$year-03-03"])->assertSessionHasErrors('start_date');
    }

    public function test_zero_carry_expires_all_remaining_days(): void
    {
        [$owner,,,$person] = $this->workspace();
        $this->actingAs($owner)->post("/employees/$person/rollover", ['year' => now()->year - 1, 'carry_limit' => 0, 'allocation' => 14])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_rollovers', ['employee_id' => $person, 'closing_days' => 6, 'carried_days' => 0]);
        $this->assertDatabaseHas('employees', ['id' => $person, 'leave_balance' => 14]);
    }

    public function test_register_reconciles_to_locked_employee_validation_copy(): void
    {
        [$owner,$staff,$company,$person] = $this->workspace();
        [$outsider,$otherStaff] = $this->workspace();
        $period = DB::table('pay_periods')->insertGetId(['company_id' => $company, 'starts_on' => '2027-01-01', 'ends_on' => '2027-01-31']);
        $url = "/pay-periods/$period";
        $data = ['employee_id' => $person, 'base' => '3000.01', 'overtime' => '150', 'allowances' => '100', 'service_charge' => '50', 'deductions' => '200'];
        $this->actingAs($outsider)->post("$url/entries", $data)->assertNotFound();
        $this->actingAs($owner)->get("$url/register?edit=$person")->assertSee('Save amounts');
        $this->post("$url/release")->assertSessionHasErrors('period');
        $this->post("$url/entries", $data)->assertSessionHasNoErrors();
        $this->post("$url/entries", $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payroll_lines', 1);
        $line = DB::table('payroll_lines')->value('id');
        $this->get("$url/register?edit=$person")->assertSee('3,100.01')->assertSee('3000.01');
        $this->actingAs($staff)->get("/payslips/$line")->assertNotFound();
        $this->actingAs($owner)->post("$url/release")->assertSessionHasNoErrors();
        $this->post("$url/entries", $data)->assertStatus(409);
        $this->actingAs($staff)->get('/payslips')->assertSee('2027-01-31');
        $this->get("/payslips/$line")->assertSee('3,300.01')->assertSee('3,100.01');
        $this->actingAs($otherStaff)->get("/payslips/$line")->assertNotFound();
    }
}
