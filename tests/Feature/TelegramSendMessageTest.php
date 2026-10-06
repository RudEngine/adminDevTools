<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Enum\TelegramEventTypeEnum;
use App\Domain\Telegram\Gateway\TelegramMessageSenderInterface;
use App\Infrastructure\Telegram\Nutgram\NutgramMessageSender;
use App\Models\TelegramChannel;
use App\MoonShine\Resources\TelegramChannel\TelegramChannelResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\Support\Telegram\FakeTelegramMessageSender;
use Tests\TestCase;

class TelegramSendMessageTest extends TestCase
{
    use RefreshDatabase;

    private const int CHAT_ID = -1003807797608;

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
            'chat_id' => self::CHAT_ID,
            'chat_name' => 'Посоны на апщении',
            'last_event_type' => TelegramEventTypeEnum::MESSAGE_REACTION,
        ]);
    }

    private function sendMessageUrl(TelegramChannel $channel): string
    {
        return app(CoreContract::class)->getRouter()->getEndpoints()->method(
            method: 'sendMessage',
            params: ['resourceItem' => $channel->getKey()],
            page: $this->resource->getIndexPage(),
            resource: $this->resource,
        );
    }

    private function postMessage(TelegramChannel $channel, string $text): TestResponse
    {
        return $this->post($this->sendMessageUrl($channel), ['text' => $text], ['Accept' => 'application/json']);
    }

    public function test_sender_interface_is_bound_to_nutgram_adapter(): void
    {
        $this->assertInstanceOf(
            NutgramMessageSender::class,
            app(TelegramMessageSenderInterface::class)
        );
    }

    public function test_index_page_shows_send_button(): void
    {
        $this->channel();

        $this->get($this->resource->getIndexPageUrl())
            ->assertSuccessful()
            ->assertSee('Отправить сообщение');
    }

    public function test_posting_text_sends_it_to_chat_of_the_row(): void
    {
        $sender = new FakeTelegramMessageSender(messageId: 99);
        $this->app->instance(TelegramMessageSenderInterface::class, $sender);

        $this->postMessage($this->channel(), 'Привет, посоны')
            ->assertSuccessful()
            ->assertJsonFragment([
                'message' => 'Сообщение отправлено в «Посоны на апщении»',
                'messageType' => 'success',
            ]);

        $this->assertSame(
            [['chatId' => self::CHAT_ID, 'text' => 'Привет, посоны']],
            $sender->sent
        );
    }

    public function test_blank_text_is_rejected_without_sending(): void
    {
        $sender = new FakeTelegramMessageSender();
        $this->app->instance(TelegramMessageSenderInterface::class, $sender);

        $this->postMessage($this->channel(), '   ')->assertUnprocessable();

        $this->assertSame([], $sender->sent);
    }

    public function test_text_longer_than_telegram_limit_is_rejected(): void
    {
        $sender = new FakeTelegramMessageSender();
        $this->app->instance(TelegramMessageSenderInterface::class, $sender);

        $this->postMessage($this->channel(), str_repeat('а', 4097))->assertUnprocessable();

        $this->assertSame([], $sender->sent);
    }

    public function test_sender_failure_is_reported_as_error(): void
    {
        $this->app->instance(
            TelegramMessageSenderInterface::class,
            new FakeTelegramMessageSender(failWith: 'Бота выгнали из чата')
        );

        $this->postMessage($this->channel(), 'Привет')
            ->assertServerError()
            ->assertJsonFragment([
                'message' => 'Бота выгнали из чата',
                'messageType' => 'error',
            ]);
    }
}
