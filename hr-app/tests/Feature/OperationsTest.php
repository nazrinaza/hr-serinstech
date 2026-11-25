<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduling_prior_income_and_synthetic_payroll(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $staff = $this->employee($company, 'Sales');
        $person = DB::table('employees')->where('user_id', $staff->id)->first();
        $this->actingAs($owner);
        foreach (['schedule', 'previous-incomes', 'payroll/example', 'company-settings'] as $screen) {
            $this->get('/'.$screen)->assertOk();
        }
        $this->patch('/company-settings', ['name' => 'Updated company'])->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertSee('Updated company');
        $this->get('/payroll/example')->assertSee('3,300.00')->assertSee('3,100.00');
        $this->post('/payroll/example', ['base' => '1000.01', 'overtime' => '0', 'allowances' => '0.20', 'service_charge' => '0', 'deductions' => '0.10'])->assertOk()->assertSee('1,000.11');
        $shift = ['employee_id' => $person->id, 'starts_at' => '2027-03-08T22:00', 'ends_at' => '2027-03-09T06:00'];
        $this->post('/schedule', $shift)->assertSessionHasNoErrors();
        $this->post('/schedule', $shift)->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('work_shifts', 1);
        $this->get('/attendance?week=2027-03-10')->assertOk()
            ->assertSeeInOrder(['Mon 8 Mar', 'Tue 9 Mar', '22:00–24:00', '00:00–06:00']);
        $this->get('/attendance?week=2027-03-17')->assertOk()->assertDontSee('22:00–24:00');
        $this->get('/attendance?search=unmatched-code')->assertSee('No employees match your search.');
        $csv = "employee_code,month,amount_myr\n{$person->code},2027-03,3150.45\n";
        $upload = fn ($body) => UploadedFile::fake()->createWithContent('income.csv', $body);
        $this->post('/previous-incomes', ['csv' => $upload($csv."unknown,2027-03,1\n")])->assertSessionHasErrors('csv');
        $this->assertDatabaseCount('prior_incomes', 0);
        foreach ([1, 2] as $attempt) {
            $this->post('/previous-incomes', ['csv' => $upload($csv)])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('prior_incomes', 1);
        $this->assertDatabaseHas('prior_incomes', ['amount_cents' => 315045]);
        $this->assertStringContainsString('2027-03,3150.45', $this->get('/reports/export?report=incomes')->streamedContent());
        $this->actingAs($staff)->get('/schedule')->assertOk()->assertSee('22:00');
        $this->get('/previous-incomes')->assertForbidden();
        $this->get('/payroll/example')->assertForbidden();
    }

    public function test_profile_tabs_persist_sensitive_fields_and_respect_company_access(): void
    {
        $owner = User::factory()->create();
        $staff = $this->employee($this->company($owner), 'Operations');
        $employee = DB::table('employees')->where('user_id', $staff->id)->value('id');
        $otherOwner = User::factory()->create();
        $this->company($otherOwner);
        $edit = "/employees/{$employee}/edit";
        $bank = "/employees/{$employee}/profile/bank";
        $this->actingAs($owner);
        foreach (config('employee-profile') as $key => $section) {
            $this->get($edit.'?tab='.$key)->assertOk()->assertSee($section['label']);
        }
        $this->patch($bank, ['sequence' => 0, 'bank_name' => 'Example Bank'])->assertSessionHasNoErrors();
        $this->get($edit.'?tab=bank')->assertSee('Example Bank');
        $this->assertStringNotContainsString('Example Bank', DB::table('employee_profile_records')->value('payload'));
        $this->actingAs($staff)->get('/employees?tab=bank')->assertSee('Example Bank')->assertDontSee('Save Bank');
        $this->patch($bank, ['sequence' => 1])->assertForbidden();
        $this->actingAs($otherOwner)->get($edit.'?tab=bank')->assertNotFound();
        $this->patch($bank, ['sequence' => 1])->assertNotFound();
    }

    public function test_owner_switches_companies_with_isolated_data_and_new_form_token(): void
    {
        $owner = User::factory()->create();
        $first = $this->company($owner);
        $staff = $this->employee($first, 'HR');
        $outsider = $this->company(User::factory()->create());
        $this->actingAs($owner)->get('/companies')->assertOk();
        $originalToken = session()->token();
        $this->post('/companies', ['name' => 'Second company'])->assertRedirect('/dashboard');
        $this->assertNotSame($originalToken, session()->token());
        $this->get('/dashboard')->assertSee('Second company');
        $this->get('/employees')->assertDontSee($staff->name);
        $this->post('/employees', ['name' => 'Second company employee', 'code' => '002'])->assertSessionHasNoErrors();
        $this->post("/companies/{$outsider}/switch")->assertNotFound();
        $this->post("/companies/{$first}/switch")->assertRedirect('/dashboard');
        $this->get('/employees')->assertSee($staff->name)->assertDontSee('Second company employee');
        $this->flushSession();
        $this->actingAs($staff)->get('/companies')->assertForbidden();
        $this->post("/companies/{$first}/switch")->assertNotFound();
    }

    private function company(User $owner): int
    {
        return DB::table('companies')->insertGetId(['name' => 'Test workspace', 'owner_id' => $owner->id]);
    }

    private function employee(int $company, string $department): User
    {
        $user = User::factory()->create();
        DB::table('employees')->insert(['company_id' => $company, 'user_id' => $user->id, 'name' => $user->name, 'code' => (string) $user->id, 'department' => $department]);

        return $user;
    }

    public function test_itemised_draft_remains_private_until_employee_submits_it(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $staff = $this->employee($company, 'Sales');
        $accounts = $this->employee($company, 'Accounts');
        $line = ['description' => 'Fare', 'spent_on' => today()->toDateString(), 'amount' => '10.10'];
        $data = ['intent' => 'draft', 'claim_type' => 'Travel', 'description' => 'Trip draft', 'items' => [$line, [...$line, 'amount' => '0.20']]];
        $this->actingAs($staff)->post('/claims', $data)->assertSessionHasNoErrors();
        $claim = DB::table('expense_claims')->first();
        $this->assertDatabaseHas('expense_claims', ['id' => $claim->id, 'amount' => '10.30', 'status' => 'draft']);
        $this->get('/claims?draft='.$claim->id)->assertSee('Edit draft claim')->assertSee('Fare');
        $this->actingAs($accounts)->get('/claims')->assertDontSee('Trip draft');
        $this->patch("/claims/$claim->id/draft", $data)->assertNotFound();
        $this->actingAs($owner)->get('/claims')->assertDontSee('Trip draft');
        $this->actingAs($staff)->patch("/claims/$claim->id/draft", [...$data, 'intent' => 'submit'])->assertSessionHasNoErrors();
        $this->patch("/claims/$claim->id/draft", $data)->assertNotFound();
        $this->actingAs($accounts)->get('/claims')->assertSee('Trip draft')->assertSee('Fare');
        $this->patch("/review-queue/claims/$claim->id", ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expense_claims', ['id' => $claim->id, 'status' => 'approved']);
    }

    public function test_claims_go_only_to_accounts_and_timesheets_only_to_hr(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $staff = $this->employee($company, 'Operations');
        $accounts = $this->employee($company, 'Account Department');
        $hr = $this->employee($company, 'Human Resources Department');
        $foreign = $this->employee($this->company(User::factory()->create()), 'Account Department');
        $this->actingAs($staff)->post('/claims', ['description' => 'Site visit', 'spent_on' => today()->toDateString(), 'amount' => '24.50', 'receipt' => UploadedFile::fake()->create('receipt.pdf', 2, 'application/pdf')])->assertSessionHasNoErrors();
        $claim = DB::table('expense_claims')->first();
        Storage::disk('local')->assertExists($claim->receipt);
        $this->post('/attendance', ['action' => 'in']);
        $this->post('/attendance', ['action' => 'in']);
        $this->assertDatabaseCount('attendance_entries', 1);
        $entry = DB::table('attendance_entries')->value('id');
        $this->post('/attendance', ['action' => 'out', 'entry_id' => $entry]);
        $this->actingAs($hr)->get('/attendance')->assertSee('HR timesheet approvals')->assertSee($staff->name);
        $this->get("/claims/$claim->id/receipt")->assertNotFound();
        $this->patch("/review-queue/claims/$claim->id", ['status' => 'approved'])->assertForbidden();
        $this->patch("/review-queue/attendance/$entry", ['status' => 'approved'])->assertRedirect();
        $this->actingAs($foreign)->get("/claims/$claim->id/receipt")->assertNotFound();
        $this->patch("/review-queue/claims/$claim->id", ['status' => 'approved'])->assertNotFound();
        $this->actingAs($accounts)->get('/claims')->assertSee('Accounts claim approvals')->assertSee('Site visit');
        $this->get("/claims/$claim->id/receipt")->assertOk();
        $this->patch("/review-queue/attendance/$entry", ['status' => 'declined'])->assertForbidden();
        $this->patch("/review-queue/claims/$claim->id", ['status' => 'approved'])->assertRedirect();
        $this->assertDatabaseHas('expense_claims', ['id' => $claim->id, 'status' => 'approved', 'reviewed_by' => $accounts->id]);
        $this->assertDatabaseHas('attendance_entries', ['id' => $entry, 'status' => 'approved', 'reviewed_by' => $hr->id]);
        $this->post('/claims', ['description' => 'My claim', 'spent_on' => today()->toDateString(), 'amount' => '10'])->assertSessionHasNoErrors();
        $own = DB::table('expense_claims')->max('id');
        $this->patch("/review-queue/claims/$own", ['status' => 'approved'])->assertNotFound();
    }

    public function test_owner_and_employee_workflows_and_reports(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $staff = $this->employee($company, 'Operations');
        $foreign = $this->employee($this->company(User::factory()->create()), 'HR');
        $this->actingAs($owner);
        foreach (['dashboard', 'posts', 'claims', 'attendance', 'reviews', 'pay-periods', 'reports'] as $path) {
            $this->get('/'.$path)->assertOk();
        }
        $this->post('/posts', ['title' => 'Company meeting', 'body' => 'Friday'])->assertSessionHasNoErrors();
        $this->post('/pay-periods', ['starts_on' => '2027-03-01', 'ends_on' => '2027-03-31'])->assertSessionHasNoErrors();
        $this->post('/pay-periods', ['starts_on' => '2027-03-31', 'ends_on' => '2027-04-30'])->assertSessionHasErrors('starts_on');
        $this->post('/reviews/templates', ['title' => 'Quarterly', 'prompt' => 'Which goals did you complete?']);
        $assignment = ['due_on' => '2027-03-31', 'template_id' => DB::table('appraisal_templates')->value('id')];
        $this->post('/reviews', [...$assignment, 'employee_id' => DB::table('employees')->where('user_id', $foreign->id)->value('id')])->assertNotFound();
        $this->post('/reviews', [...$assignment, 'employee_id' => DB::table('employees')->where('user_id', $staff->id)->value('id')])->assertSessionHasNoErrors();
        $id = DB::table('appraisals')->value('id');
        $report = $this->get('/reports/export?report=employees')->assertOk()->streamedContent();
        $this->assertStringContainsString($staff->name, $report);
        $this->assertStringNotContainsString($foreign->name, $report);
        $this->actingAs($foreign)->get('/posts')->assertDontSee('Company meeting');
        $this->patch("/reviews/$id", ['response' => 'Other'])->assertNotFound();
        $this->actingAs($staff)->get('/dashboard')->assertSee('Company meeting');
        $this->get('/reports')->assertForbidden();
        $this->post('/posts', ['title' => 'test', 'body' => 'test'])->assertForbidden();
        $this->patch("/reviews/$id", ['response' => 'Completed onboarding'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch("/reviews/$id", ['feedback' => 'Reviewed'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->get('/reviews')->assertSee('Reviewed');
    }
}
