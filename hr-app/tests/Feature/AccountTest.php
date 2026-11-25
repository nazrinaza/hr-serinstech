<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_change_only_their_own_profile_with_current_password(): void
    {
        $owner = User::factory()->create();
        DB::table('companies')->insert(['owner_id' => $owner->id, 'name' => 'Alpha']);
        $this->actingAs($owner)->get('/account')->assertOk()->assertSee($owner->email);
        $data = ['name' => 'New name', 'email' => 'changed@example.test', 'password' => 'new-password-value', 'password_confirmation' => 'new-password-value'];
        $this->patch('/account', [...$data, 'current_password' => 'incorrect'])->assertSessionHasErrors('current_password');
        $this->patch('/account', [...$data, 'current_password' => 'password'])->assertRedirect('/account');
        $this->get('/account')->assertSee('changed@example.test');
        $this->post('/logout');
        $this->post('/login', ['email' => $data['email'], 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post('/login', $data)->assertRedirect('/employees');
    }

    public function test_company_owner_controls_only_linked_employee_logins(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $staff = User::factory()->create();
        $company = DB::table('companies')->insertGetId(['owner_id' => $owner->id, 'name' => 'Alpha']);
        DB::table('companies')->insert(['owner_id' => $other->id, 'name' => 'Beta']);
        $id = DB::table('employees')->insertGetId(['company_id' => $company, 'user_id' => $staff->id, 'code' => '01', 'name' => 'Staff']);
        $url = "/users/$id/login";
        $this->actingAs($other)->get('/users')->assertDontSee($staff->email);
        $this->patch($url, ['enabled' => 0])->assertNotFound();
        $this->actingAs($staff)->get('/users')->assertForbidden();
        $this->actingAs($owner)->get('/users')->assertOk()->assertSee($staff->email);
        $this->patch($url, ['enabled' => 0])->assertRedirect('/users');
        $this->actingAs($staff->fresh())->get('/account')->assertRedirect('/');
        $login = ['email' => $staff->email, 'password' => 'password'];
        $this->post('/login', $login)->assertSessionHasErrors('email');
        $this->actingAs($owner)->patch($url, ['enabled' => 1])->assertRedirect('/users');
        $this->actingAs($staff->fresh())->get('/account')->assertRedirect('/');
        $this->post('/login', $login)->assertRedirect('/employees');
        $this->get('/account')->assertOk()->assertSee($staff->email);
    }
}
