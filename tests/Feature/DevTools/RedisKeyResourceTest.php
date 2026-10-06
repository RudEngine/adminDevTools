<?php

declare(strict_types=1);

namespace Tests\Feature\DevTools;

use App\Domain\DevTools\Redis\RedisKeyBrowserInterface;
use App\MoonShine\Resources\RedisKey\RedisKeyResource;
use App\MoonShine\Resources\RedisKey\Support\RedisKeyId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\Support\DevTools\InMemoryRedisKeyBrowser;
use Tests\TestCase;

class RedisKeyResourceTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryRedisKeyBrowser $browser;

    private RedisKeyResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->browser = new InMemoryRedisKeyBrowser;
        $this->app->instance(RedisKeyBrowserInterface::class, $this->browser);

        $this->resource = app(RedisKeyResource::class);
    }

    private function login(): void
    {
        $role = MoonshineUserRole::create(['name' => 'Admin']);

        $this->be(MoonshineUser::create([
            'moonshine_user_role_id' => $role->getKey(),
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ]), 'moonshine');
    }

    public function test_index_page_lists_keys_of_the_first_connection(): void
    {
        $this->login();
        $this->browser->put('default', 'telegram:channel:chat:1', 'значение');

        $this->get($this->resource->getIndexPageUrl())
            ->assertSuccessful()
            ->assertSee('telegram:channel:chat:1')
            ->assertSee('string');

        $this->assertSame('default', $this->browser->lastScan['connection']);
        $this->assertSame('*', $this->browser->lastScan['pattern']);
    }

    public function test_connection_and_mask_filters_reach_redis(): void
    {
        $this->login();
        $this->browser->put('cache', 'telegram:channel:chat:1', 'значение');
        $this->browser->put('cache', 'session:abc', 'другое');

        $this->get($this->resource->getIndexPageUrl([
            'filter' => ['connection' => 'cache', 'pattern' => 'telegram:*'],
        ]))
            ->assertSuccessful()
            ->assertSee('telegram:channel:chat:1')
            ->assertDontSee('session:abc');

        $this->assertSame('cache', $this->browser->lastScan['connection']);
        $this->assertSame('telegram:*', $this->browser->lastScan['pattern']);
    }

    /**
     * Ключи показываются «как есть», вместе с префиксом Laravel, поэтому маску
     * без него не составить — префикс надо подсказать прямо на странице.
     */
    public function test_index_page_hints_the_active_key_prefix(): void
    {
        config()->set('database.redis.options.prefix', 'admin-database-');

        $this->login();

        $this->get($this->resource->getIndexPageUrl())
            ->assertSuccessful()
            ->assertSee('admin-database-');
    }

    public function test_hitting_the_scan_limit_is_announced(): void
    {
        config()->set('devtools.redis.scan_limit', 1);

        $this->login();
        $this->browser->put('default', 'key:1', 'a');
        $this->browser->put('default', 'key:2', 'b');

        $this->get($this->resource->getIndexPageUrl())
            ->assertSuccessful()
            ->assertSee('уточните маску');

        $this->assertSame(1, $this->browser->lastScan['limit']);
    }

    public function test_detail_page_shows_the_unpacked_value(): void
    {
        $this->login();
        $this->browser->put('default', 'telegram:channel:chat:1', serialize(['chat_name' => 'Посоны']));

        $id = (new RedisKeyId('default', 'telegram:channel:chat:1'))->toString();

        $this->get($this->resource->getDetailPageUrl($id))
            ->assertSuccessful()
            ->assertSee('telegram:channel:chat:1')
            ->assertSee('Посоны');
    }

    public function test_detail_page_for_a_missing_key_is_not_found(): void
    {
        $this->login();

        $id = (new RedisKeyId('default', 'нет-такого'))->toString();

        $this->get($this->resource->getDetailPageUrl($id))->assertNotFound();
    }

    public function test_key_is_deleted_from_redis(): void
    {
        $this->login();
        $this->browser->put('default', 'doomed', 'a');

        $id = (new RedisKeyId('default', 'doomed'))->toString();

        $this->delete($this->resource->getRoute('crud.destroy', $id))
            ->assertRedirect();

        $this->assertNull($this->browser->find('default', 'doomed'));
    }

    public function test_menu_contains_the_devtools_section(): void
    {
        $this->login();

        $this->get($this->resource->getIndexPageUrl())
            ->assertSuccessful()
            ->assertSee('DevTools')
            ->assertSee($this->resource->getIndexPageUrl());
    }

    public function test_guest_is_redirected_from_the_redis_page(): void
    {
        $this->get($this->resource->getIndexPageUrl())
            ->assertRedirect(moonshineRouter()->to('login'));
    }
}
