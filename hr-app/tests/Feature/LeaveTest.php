<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_withdraws_only_own_pending_leave_and_can_reapply(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $company = DB::table('companies')->insertGetId(['name' => 'Company', 'owner_id' => User::factory()->create()->id]);
        foreach ([$user, $other] as $person) {
            DB::table('employees')->insert(['user_id' => $person->id, 'company_id' => $company, 'name' => $person->name, 'code' => $person->id, 'leave_balance' => 5]);
        }
        $dates = ['start_date' => '2027-03-05', 'end_date' => '2027-03-05'];
        $this->actingAs($user)->post('/leave', $dates)->assertSessionHasNoErrors();
        $id = DB::table('leave_requests')->value('id');
        $path = "/leave/$id/withdraw";
        $this->actingAs($other)->patch($path)->assertNotFound();
        $this->actingAs($user)->get('/leave')->assertSee('Withdraw request');
        $this->patch($path)->assertRedirect(route('leave'));
        $this->patch($path)->assertRedirect(route('leave'));
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'withdrawn']);
        $this->assertDatabaseHas('employees', ['user_id' => $user->id, 'leave_balance' => 5]);
        $this->post('/leave', $dates)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('leave_requests', 2);
        foreach (['approved', 'declined'] as $status) {
            DB::table('leave_requests')->where('id', $id)->update(['status' => $status]);
            $this->patch($path)->assertSessionHasErrors('status');
            $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => $status]);
        }
    }

    public function test_returning_to_hr_requires_a_fresh_approval_grant(): void
    {
        $owner = User::factory()->create();
        $hr = User::factory()->create();
        $company = DB::table('companies')->insertGetId(['name' => 'Company', 'owner_id' => $owner->id]);
        $profile = ['name' => 'Reviewer', 'code' => 'R1'];
        $id = DB::table('employees')->insertGetId([...$profile, 'user_id' => $hr->id, 'company_id' => $company, 'department' => 'HR', 'hr_approver' => true]);
        foreach (['Operations', 'Human Resources'] as $department) {
            $this->actingAs($owner)->patch("/employees/$id", [...$profile, 'department' => $department])->assertSessionHasNoErrors();
        }
        $this->actingAs($hr)->get('/leave')->assertDontSee('HR approval queue');
    }

    public function test_only_designated_same_company_hr_can_decide_and_charge_once(): void
    {
        $owner = User::factory()->create();
        $company = DB::table('companies')->insertGetId(['name' => 'Alpha', 'owner_id' => $owner->id]);
        $employee = User::factory()->create();
        $hr = User::factory()->create();
        $staffId = DB::table('employees')->insertGetId(['company_id' => $company, 'user_id' => $employee->id, 'code' => '01', 'name' => 'Staff']);
        $hrId = DB::table('employees')->insertGetId(['company_id' => $company, 'user_id' => $hr->id, 'code' => '02', 'name' => 'Reviewer', 'department' => 'Human Resources']);
        $this->actingAs($owner)->patch("/employees/$staffId/leave-settings", ['opening_days' => 10, 'hr_approver' => 1])->assertSessionHasErrors('hr_approver');
        $this->patch("/employees/$staffId/leave-settings", ['opening_days' => 10])->assertSessionHasNoErrors();
        $dates = ['start_date' => '2027-03-05', 'end_date' => '2027-03-08'];
        $this->actingAs($employee)->post('/leave', $dates)->assertSessionHasNoErrors();
        $this->post('/leave', $dates)->assertSessionHasErrors('start_date');
        $id = DB::table('leave_requests')->value('id');
        $url = "/leave/$id";
        $this->patch($url, ['status' => 'approved'])->assertForbidden();
        $this->actingAs($hr)->patch($url, ['status' => 'approved'])->assertForbidden();
        $this->actingAs($owner)->patch($url, ['status' => 'approved'])->assertForbidden();
        $this->patch("/employees/$hrId/leave-settings", ['opening_days' => 10, 'hr_approver' => 1])->assertSessionHasNoErrors();
        $this->actingAs($hr)->get('/leave')->assertSee('HR approval queue')->assertSee('Staff');
        $this->post('/leave', $dates)->assertSessionHasNoErrors();
        $ownId = DB::table('leave_requests')->where('employee_id', $hrId)->value('id');
        $this->patch("/leave/$ownId", ['status' => 'approved'])->assertForbidden();
        $foreignCompany = DB::table('companies')->insertGetId(['name' => 'Beta', 'owner_id' => User::factory()->create()->id]);
        $foreignHr = User::factory()->create();
        DB::table('employees')->insert(['company_id' => $foreignCompany, 'user_id' => $foreignHr->id, 'name' => 'Other', 'code' => 'HR', 'department' => 'HR', 'hr_approver' => true]);
        $this->actingAs($foreignHr)->patch($url, ['status' => 'approved'])->assertNotFound();
        $this->actingAs($hr)->patch($url, ['status' => 'approved'])->assertRedirect(route('leave'));
        $this->patch($url, ['status' => 'approved'])->assertRedirect(route('leave'));
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'approved', 'reviewed_by' => $hr->id, 'days' => 2]);
        $this->assertDatabaseHas('employees', ['id' => $staffId, 'leave_balance' => 8]);
        $this->actingAs($employee)->post('/leave', ['start_date' => '2027-03-09', 'end_date' => '2027-03-09'])->assertSessionHasNoErrors();
        $lastId = DB::table('leave_requests')->max('id');
        $this->actingAs($hr)->patch("/leave/$lastId", ['status' => 'declined'])->assertRedirect(route('leave'));
        $this->assertDatabaseHas('leave_requests', ['id' => $lastId, 'status' => 'declined']);
        $this->assertDatabaseHas('employees', ['id' => $staffId, 'leave_balance' => 8]);

    }
}
