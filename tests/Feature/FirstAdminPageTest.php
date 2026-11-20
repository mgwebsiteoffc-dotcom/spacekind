<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FirstAdminPageTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://localhost/admin/setup';
    private string $temp;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spacekind.demo' => false]);
        $this->temp = sys_get_temp_dir().'/studio-test-'.bin2hex(random_bytes(10));
        mkdir($this->temp.'/framework', 0700, true);
        $this->app->useStoragePath($this->temp);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temp);
        parent::tearDown();
    }

    private function credentials(): array
    {
        $password = bin2hex(random_bytes(16));

        return ['email' => 'installer@example.test', 'password' => $password, 'password_confirmation' => $password];
    }

    private function challenge(): string
    {
        $page = $this->get(self::URL)->assertOk()->assertSee('Confirm hosting access');
        $this->assertMatchesRegularExpression('/approve-admin-[a-zA-Z0-9]{32}\.txt/', $page->getContent());
        preg_match('/approve-admin-[a-zA-Z0-9]{32}\.txt/', $page->getContent(), $match);

        return storage_path('framework/'.$match[0]);
    }

    public function test_only_current_approved_session_can_create_once_and_login(): void
    {
        $data = $this->credentials();
        $file = $this->challenge();
        $this->post(self::URL, $data)->assertSessionHasErrors('approval');
        touch($file);
        session()->flush();
        $next = $this->challenge();
        $this->assertNotSame($file, $next);
        $this->post(self::URL, $data)->assertSessionHasErrors('approval');
        $this->assertDatabaseCount('users', 0);
        touch($next);
        $this->post(self::URL, $data)->assertRedirect('/login');
        $this->assertFileDoesNotExist($next);
        $this->assertDatabaseHas('users', ['email' => $data['email'], 'role' => 'super_admin', 'is_active' => true]);
        $this->assertGuest();
        $this->get(self::URL)->assertNotFound();
        $this->post('/login', $data)->assertRedirect('/admin');
    }

    public function test_setup_rejects_http_expired_challenges_and_existing_accounts(): void
    {
        $this->get('http://localhost/admin/setup')->assertForbidden();
        $file = $this->challenge();
        touch($file);
        $data = $this->credentials();
        $member = User::factory()->create(['email' => $data['email']]);
        $this->post(self::URL, $data)->assertSessionHasErrors('email');
        $this->assertFalse($member->fresh()->isAdmin());
        $this->travel(31)->minutes();
        $this->post(self::URL, $this->credentials())->assertSessionHasErrors('approval');
        $member->forceFill(['role' => 'super_admin', 'is_active' => false])->save();
        $this->get(self::URL)->assertNotFound();
    }
}
