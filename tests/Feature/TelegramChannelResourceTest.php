<?php

namespace Tests\Feature;

use App\Domain\Enum\TelegramEventTypeEnum;
use App\Models\TelegramChannel;
use App\MoonShine\Resources\TelegramChannel\TelegramChannelResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class TelegramChannelResourceTest extends TestCase
{
    use RefreshDatabase;

    private TelegramChannelResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resource = app(TelegramChannelResource::class);

        $role = MoonshineUserRole::create(['name' => 'Admin']);

        $this->be(MoonshineUser::create([
            'moonshine_user_role_id' => $role->getKey(),
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ]), 'moonshine');
    }

    private function channel(): TelegramChannel
    {
        return TelegramChannel::create([
            'chat_id' => -1003807797608,
            'chat_name' => 'Посоны на апщении',
            'last_event_type' => TelegramEventTypeEnum::MESSAGE_REACTION,
        ]);
    }

    public function test_index_page_is_successful(): void
    {
        $this->channel();

        $this->get($this->resource->getIndexPageUrl())
            ->assertSuccessful()
            ->assertSee('Посоны на апщении');
    }

    public function test_menu_contains_telegram_group(): void
    {
        $this->get($this->resource->getIndexPageUrl())
            ->assertSuccessful()
            ->assertSee('Телеграм')
            ->assertSee($this->resource->getIndexPageUrl());
    }

    public function test_edit_page_is_successful(): void
    {
        $channel = $this->channel();

        $this->get($this->resource->getFormPageUrl($channel->getKey()))
            ->assertSuccessful()
            ->assertSee('Посоны на апщении');
    }
}
