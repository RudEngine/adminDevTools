<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Один контейнер обслуживает два домена: app.domain (админка) и app.api_domain (API).
 * Разделение — только на уровне роутинга Laravel.
 */
class DomainRoutingTest extends TestCase
{
    private function webUrl(string $path = '/'): string
    {
        return 'http://'.config('app.domain').$path;
    }

    private function apiUrl(string $path = '/'): string
    {
        return 'http://'.config('app.api_domain').$path;
    }

    public function test_api_route_is_reachable_on_api_domain(): void
    {
        // Без секрета вебхук отвечает 403 — важно лишь, что маршрут найден.
        $this->postJson($this->apiUrl('/webhook'), ['update_id' => 1])
            ->assertForbidden();
    }

    public function test_api_route_is_not_matched_on_web_domain(): void
    {
        // На основном домене путь ловит только GET-fallback из web.php
        // (редирект в админку), поэтому POST получает 405, а не 403 вебхука.
        $this->postJson($this->webUrl('/webhook'), ['update_id' => 1])
            ->assertMethodNotAllowed();

        $this->get($this->webUrl('/webhook'))
            ->assertRedirect(moonshineRouter()->getEndpoints()->home());
    }

    public function test_home_on_web_domain_redirects_to_admin(): void
    {
        // Публичной главной нет: корень уводит в MoonShine.
        $this->get($this->webUrl('/'))
            ->assertRedirect(moonshineRouter()->getEndpoints()->home());
    }

    public function test_home_on_api_domain_is_not_found(): void
    {
        $this->get($this->apiUrl('/'))->assertNotFound();
    }

    public function test_unknown_path_on_api_domain_is_not_found(): void
    {
        $this->get($this->apiUrl('/no-such-page'))->assertNotFound();
    }

    public function test_admin_is_not_found_on_api_domain(): void
    {
        $this->get($this->apiUrl('/'.config('moonshine.prefix').'/login'))->assertNotFound();
    }

    public function test_admin_login_is_reachable_on_web_domain(): void
    {
        $this->get($this->webUrl('/'.config('moonshine.prefix').'/login'))->assertOk();
    }

    public function test_every_route_except_health_is_bound_to_a_domain(): void
    {
        $unbound = collect(Route::getRoutes()->getRoutes())
            ->reject(fn ($route) => $route->uri() === 'up')
            ->filter(fn ($route) => blank($route->getDomain()))
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unbound);
    }

    public function test_health_on_api_domain_is_json(): void
    {
        $this->get($this->apiUrl('/up'), ['Accept' => 'text/html'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['status' => 'up']);
    }

    public function test_health_on_web_domain_stays_html(): void
    {
        $response = $this->get($this->webUrl('/up'), ['Accept' => 'text/html']);

        $response->assertOk();
        $this->assertStringStartsWith('text/html', $response->headers->get('Content-Type'));
    }

    public function test_errors_on_api_domain_are_json_even_for_browser(): void
    {
        $this->get($this->apiUrl('/no-such-page'), ['Accept' => 'text/html'])
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');

        $this->get($this->apiUrl('/webhook'), ['Accept' => 'text/html'])
            ->assertMethodNotAllowed()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_maintenance_mode_on_api_domain_is_json(): void
    {
        $this->app->maintenanceMode()->activate([]);

        try {
            $this->get($this->apiUrl('/no-such-page'), ['Accept' => 'text/html'])
                ->assertServiceUnavailable()
                ->assertHeader('Content-Type', 'application/json');
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }
    }

    public function test_url_uses_https_behind_proxy(): void
    {
        Route::domain(config('app.domain'))
            ->get('/__test/url', fn () => url('/'));

        $response = $this->get($this->webUrl('/__test/url'), ['X-Forwarded-Proto' => 'https']);

        $response->assertOk();
        $this->assertStringStartsWith('https://', $response->getContent());
    }
}
