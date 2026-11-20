<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class StudioNavigationAndBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private function provision(string $email, string $password): void
    {
        $repo = Env::getRepository();
        $repo->set('SPACEKIND_ADMIN_EMAIL', $email);
        $repo->set('SPACEKIND_ADMIN_PASSWORD', $password);
        try {
            $this->seed(SuperAdminSeeder::class);
        } finally {
            $repo->clear('SPACEKIND_ADMIN_EMAIL');
            $repo->clear('SPACEKIND_ADMIN_PASSWORD');
        }
    }

    public function test_seeder_creates_a_password_protected_admin_only_once(): void
    {
        $email = 'private-admin@example.test';
        $this->provision($email, 'private-testing-only-123');
        $this->provision($email, 'different-testing-only-456');
        $admin = User::where('email', $email)->sole();
        $this->assertTrue($admin->isAdmin() && $admin->is_active);
        $this->assertNull($admin->business_id);
        $this->assertTrue(Hash::check('private-testing-only-123', $admin->password));
        $this->post('/login', ['email' => $email, 'password' => 'private-testing-only-123'])->assertRedirect('/admin');
    }

    public function test_seeder_rejects_unsafe_inputs_and_existing_studio_accounts(): void
    {
        $member = User::factory()->create();
        foreach ([[$member->email, 'private-testing-only-123'], ['admin@spacekind.example', 'private-testing-only-123'], ['other@example.test', ''], ['', 'private-testing-only-123']] as [$email, $password]) {
            try {
                $this->provision($email, $password);
                $this->fail('Provisioning should have been refused.');
            } catch (RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertFalse($member->fresh()->isAdmin());
    }

    public function test_navigation_and_create_shortcuts_follow_studio_permissions(): void
    {
        $studio = Business::create(['slug' => 'nav-studio', 'name' => 'Nav studio', 'city' => 'Noida']);
        $member = User::factory()->create();
        $member->forceFill(['business_id' => $studio->id, 'role' => 'owner', 'is_active' => true])->save();
        $this->actingAs($member)->get('/workspace/labour/attendance')->assertOk()->assertSee('Quick create')->assertSee('Portfolio & marketing')->assertSee('/workspace/finance/documents/new?kind=quotation')->assertSee('/workspace/labour#add-worker');
        $member->forceFill(['role' => 'staff'])->save();
        $this->actingAs($member)->get('/workspace/enquiries')->assertOk()->assertSee('/workspace/enquiries/new')->assertDontSee('Quick create')->assertDontSee('/workspace/finance/documents/new')->assertDontSee('Portfolio & marketing');
        $this->get('/workspace/finance/documents/new?kind=invoice')->assertForbidden();
    }
}
