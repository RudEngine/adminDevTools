<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Tests\TestCase;

class MoonShineOnlyAccessTest extends TestCase
{
    public function test_root_redirects_to_moonshine(): void
    {
        $this->get('/')->assertRedirect(moonshineRouter()->getEndpoints()->home());
    }

    public function test_unknown_url_redirects_to_moonshine(): void
    {
        $this->get('/no-such-page')->assertRedirect(moonshineRouter()->getEndpoints()->home());
    }

    public function test_guest_is_redirected_from_admin_to_login(): void
    {
        $this->get(moonshineRouter()->getEndpoints()->home())
            ->assertRedirect(moonshineRouter()->to('login'));
    }

    public function test_login_page_is_reachable_for_guest(): void
    {
        $this->get(moonshineRouter()->to('login'))->assertOk();
    }

    public function test_moonshine_is_the_default_guard(): void
    {
        $this->assertSame('moonshine', config('auth.defaults.guard'));
    }

    public function test_no_auth_guard_besides_moonshine_exists(): void
    {
        $this->assertSame(['moonshine'], array_keys(config('auth.guards')));
        $this->assertSame(['moonshine'], array_keys(config('auth.providers')));
    }

    public function test_web_guard_cannot_be_resolved(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Auth::guard('web');
    }

    public function test_password_reset_brokers_are_disabled(): void
    {
        $this->assertSame([], config('auth.passwords'));
    }
}
