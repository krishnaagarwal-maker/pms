<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_assign_a_role_from_a_dropdown(): void
    {
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'team_member', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'team lead', 'guard_name' => 'web']);

        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $user = User::factory()->create();

        $this->actingAs($manager);

        $response = $this->get('/projects/users/roles');
        $response->assertOk();
        $response->assertSee('team_member');
        $response->assertSee('team lead');

        $postResponse = $this->post('/projects/users/roles', [
            'user_id' => $user->id,
            'role' => 'team lead',
        ]);

        $postResponse->assertRedirect('/projects/users/roles');
        $this->assertTrue($user->fresh()->hasRole('team lead'));
    }
}
