<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['username' => 'koaung']);
    }

    private function wrong(int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->post('/login', ['login' => 'koaung', 'password' => 'wrong-pass']);
        }
    }

    public function test_waiting_time_after_too_many_wrong_tries(): void
    {
        Setting::put('security.login_max', '5');
        Setting::put('security.login_wait', '15');
        $this->user();

        $this->wrong(4);
        $this->post('/login', ['login' => 'koaung', 'password' => 'wrong-pass'])
            ->assertSessionHasErrors(['login' => __('Too many wrong tries. Please wait :time and try again.', ['time' => '15 minutes'])])
            ->assertSessionHas('login_wait_until');

        // Even the right password must wait
        $this->post('/login', ['login' => 'koaung', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();

        // Countdown on the login page
        $this->withSession(['login_wait_until' => now()->getTimestamp() + 900])->get('/login')->assertSee('data-login-wait', false);

        $this->travel(16)->minutes();
        $this->post('/login', ['login' => 'koaung', 'password' => 'password'])->assertSessionHasNoErrors();
    }

    public function test_tries_left_warning(): void
    {
        $this->user();
        $this->wrong(2);
        $this->post('/login', ['login' => 'koaung', 'password' => 'wrong-pass'])
            ->assertSessionHasErrors(['login' => __('These credentials do not match our records.').' 2 tries left before a waiting time.']);
    }

    public function test_wait_doubles_each_time(): void
    {
        Setting::put('security.login_wait', '10');
        Setting::put('security.login_progressive', '1');
        $this->user();

        $this->wrong(5);
        $first = session('login_wait_until') - now()->getTimestamp();
        $this->travel(11)->minutes();

        $this->wrong(5);
        $second = session('login_wait_until') - now()->getTimestamp();

        $this->assertEqualsWithDelta(600, $first, 5);
        $this->assertEqualsWithDelta(1200, $second, 5);
    }

    public function test_one_ip_guessing_many_accounts_is_stopped(): void
    {
        Setting::put('security.login_ip_max', '10');
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['login' => 'user'.$i, 'password' => 'x']);
        }
        $this->post('/login', ['login' => 'someone-else', 'password' => 'x'])
            ->assertSessionHasErrors('login')->assertSessionHas('login_wait_until');
    }

    public function test_owner_can_change_limits(): void
    {
        $owner = User::factory()->create();
        $owner->forceFill(['role' => 'admin', 'staff_role' => 'owner'])->save();

        $this->actingAs($owner)->put(route('admin.settings.security'), [
            'provider' => 'none', 'min_score' => '0.5', 'login_max' => 3, 'login_wait' => 30, 'login_ip_max' => 50, 'login_progressive' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertSame('3', setting('security.login_max'));
        $this->assertSame('30', setting('security.login_wait'));
        $this->assertSame('0', setting('security.login_progressive'));
    }
}
