<?php

namespace Tests\Feature\Web;

use App\Enums\UserRole;
use App\Models\User;
use Tests\Feature\DatabaseTestCase;

class AdminReportAccessTest extends DatabaseTestCase
{
    public function test_guest_is_redirected_to_login_from_admin_reports(): void
    {
        $this->get('/admin/reports')
            ->assertRedirect('/login');
    }

    public function test_admin_can_access_admin_reports(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk();
    }

    public function test_regular_user_cannot_access_admin_reports(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $this->actingAs($user)
            ->get('/admin/reports')
            ->assertForbidden();
    }
}
