<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/profile';

    public function test_guest_cannot_access_profile(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_admin_can_view_own_profile(): void
    {
        $admin = $this->actingAsAdmin(['email' => 'me@zaina.test']);

        $this->getJson(self::ENDPOINT)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.email', 'me@zaina.test');
    }

    public function test_admin_can_update_own_profile(): void
    {
        $admin = $this->actingAsAdmin();

        $this->putJson(self::ENDPOINT, [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@zaina.test',
            'phone_number' => '0599999999',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'first_name' => 'Updated',
            'email' => 'updated@zaina.test',
            'phone_number' => '0599999999',
        ]);
    }

    public function test_profile_update_rejects_an_email_used_by_another_user(): void
    {
        $this->actingAsAdmin();
        User::factory()->operation()->create(['email' => 'taken@zaina.test']);

        $this->putJson(self::ENDPOINT, [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'taken@zaina.test',
            'phone_number' => '0591111111',
        ])->assertStatus(422)->assertJsonStructure(['params' => ['email']]);
    }

    public function test_admin_can_change_own_password_with_correct_current_password(): void
    {
        $admin = $this->actingAsAdmin(['password' => Hash::make('oldsecret')]);

        $this->putJson(self::ENDPOINT.'/password', [
            'current_password' => 'oldsecret',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertStatus(200);

        $this->assertTrue(Hash::check('newsecret123', $admin->fresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $this->actingAsAdmin(['password' => Hash::make('oldsecret')]);

        $this->putJson(self::ENDPOINT.'/password', [
            'current_password' => 'wrongpass',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertStatus(422)->assertJsonStructure(['params' => ['current_password']]);
    }

    public function test_change_password_requires_matching_confirmation(): void
    {
        $this->actingAsAdmin(['password' => Hash::make('oldsecret')]);

        $this->putJson(self::ENDPOINT.'/password', [
            'current_password' => 'oldsecret',
            'password' => 'newsecret123',
            'password_confirmation' => 'different',
        ])->assertStatus(422)->assertJsonStructure(['params' => ['password']]);
    }
}
