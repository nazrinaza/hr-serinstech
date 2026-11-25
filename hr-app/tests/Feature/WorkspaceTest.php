<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_edits_profile_and_employee_redeems_a_single_use_invite(): void
    {
        $owner = User::factory()->create();
        $companyId = DB::table('companies')->insertGetId(['name' => 'Sample', 'owner_id' => $owner->id]);
        $employeeId = DB::table('employees')->insertGetId(['company_id' => $companyId, 'code' => '01', 'name' => 'Starter']);
        DB::table('employees')->insert(['company_id' => $companyId, 'code' => '02', 'name' => 'Private Colleague']);
        $otherOwner = User::factory()->create();
        DB::table('companies')->insert(['name' => 'Other', 'owner_id' => $otherOwner->id]);
        $url = "/employees/$employeeId";
        $details = ['code' => '01', 'name' => 'Updated Employee', 'department' => 'Support'];
        $this->actingAs($otherOwner)->get("$url/edit")->assertNotFound();
        $this->patch($url, $details)->assertNotFound();
        $this->post("$url/invite")->assertNotFound();
        $this->actingAs($owner)->get("$url/edit")->assertOk()->assertSee('Starter');
        $this->patch($url, [...$details, 'code' => '02'])->assertSessionHasErrors('code');
        $this->patch($url, $details)->assertRedirect(route('employees'));
        $this->assertDatabaseHas('employees', $details);
        $this->post("$url/invite")->assertSessionHas('invite_code');
        $expired = session('invite_code');
        $this->travel(25)->hours();
        $this->post('/logout');
        $credentials = ['email' => 'member@example.test', 'password' => 'synthetic-passphrase', 'password_confirmation' => 'synthetic-passphrase'];
        $this->post('/join', [...$credentials, 'invite_code' => $expired])->assertSessionHasErrors('invite_code');
        $this->actingAs($owner)->post("$url/invite")->assertSessionHas('invite_code');
        $code = session('invite_code');
        $this->get("$url/edit")->assertSee($code);
        $this->post('/logout');
        $this->get('/join')->assertOk();
        $this->post('/join', [...$credentials, 'invite_code' => $code])->assertRedirect(route('employees'));
        $this->get('/employees')->assertSee('Updated Employee')->assertDontSee('Private Colleague')->assertDontSee('Add employee');
        $this->get("$url/edit")->assertForbidden();
        $this->patch($url, $details)->assertForbidden();
        $this->post('/employees', $details)->assertForbidden();
        $this->post("$url/invite")->assertForbidden();
        $this->post('/logout');
        $this->post('/join', [...$credentials, 'email' => 'another@example.test', 'invite_code' => $code])->assertSessionHasErrors('invite_code');
        $this->post('/login', $credentials)->assertRedirect(route('employees'));
        $this->get('/employees')->assertOk()->assertSee('My profile');
    }

    public function test_owners_can_manage_only_their_company_directory(): void
    {
        $this->get('/employees')->assertRedirect(route('login'));
        $password = 'Example-only-passphrase';
        foreach (['Alpha', 'Beta'] as $company) {
            $this->post('/register', [
                'company' => $company, 'name' => 'Owner', 'email' => $company.'@example.test',
                'password' => $password, 'password_confirmation' => $password,
            ])->assertRedirect(route('employees'));
            $this->post('/employees', [
                'company_id' => DB::table('companies')->where('name', 'Alpha')->value('id'),
                'name' => $company.' Employee', 'code' => 'E01',
            ])->assertSessionHasNoErrors()->assertRedirect(route('employees'));
            $this->get('/employees')->assertOk()->assertSee($company.' Employee');
            $this->post('/logout')->assertRedirect(route('login'));
            $this->assertGuest();
        }

        $this->post('/login', ['email' => 'Alpha@example.test', 'password' => 'incorrect'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'Alpha@example.test', 'password' => $password])
            ->assertRedirect(route('employees'));
        $this->get('/employees')->assertSee('Alpha Employee')->assertDontSee('Beta Employee');
        $this->post('/employees', ['name' => 'Duplicate', 'code' => 'E01'])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('employees', 2);
    }
}
