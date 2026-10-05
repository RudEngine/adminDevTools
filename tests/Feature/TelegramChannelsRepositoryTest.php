<?php

namespace Tests\Feature;

use App\Application\UseCase\Telegram\HandleWebhook\HandleWebhookInput;
use App\Application\UseCase\Telegram\HandleWebhook\HandleWebhookUseCase;
use App\Domain\Enum\TelegramEventTypeEnum;
use App\Domain\Telegram\Entity\TelegramChannels;
use App\Domain\Telegram\Repository\TelegramChannelsRepositoryInterface;
use App\Infrastructure\Repository\Postgress\Telegram\TelegramChannels\TelegramChannelsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramChannelsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private const int CHAT_ID = -1003807797608;

    private function repository(): TelegramChannelsRepositoryInterface
    {
        return app(TelegramChannelsRepositoryInterface::class);
    }

    private function entity(string $chatName = 'Посоны на апщении'): TelegramChannels
    {
        return new TelegramChannels(
            telegramEventType: TelegramEventTypeEnum::MESSAGE_REACTION,
            chatId: self::CHAT_ID,
            chatName: $chatName,
        );
    }

    public function test_interface_is_bound_to_postgress_repository(): void
    {
        $this->assertInstanceOf(TelegramChannelsRepository::class, $this->repository());
    }

    public function test_save_creates_row_and_returns_entity_with_id(): void
    {
        $channel = $this->repository()->save($this->entity());

        $this->assertNotNull($channel->id);
        $this->assertTrue($channel->isStored());
        $this->assertSame(self::CHAT_ID, $channel->chatId);

        $this->assertDatabaseHas('telegram_channels', [
            'chat_id' => self::CHAT_ID,
            'chat_name' => 'Посоны на апщении',
            'last_event_type' => TelegramEventTypeEnum::MESSAGE_REACTION->value,
        ]);
    }

    public function test_save_updates_existing_channel_with_same_chat_id(): void
    {
        $first = $this->repository()->save($this->entity());
        $second = $this->repository()->save($this->entity('Переименованный чат'));

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('telegram_channels', 1);
        $this->assertDatabaseHas('telegram_channels', ['chat_name' => 'Переименованный чат']);
    }

    public function test_find_by_chat_id(): void
    {
        $this->assertNull($this->repository()->findByChatId(self::CHAT_ID));

        $this->repository()->save($this->entity());

        $found = $this->repository()->findByChatId(self::CHAT_ID);

        $this->assertNotNull($found);
        $this->assertSame('Посоны на апщении', $found->chatName);
        $this->assertSame(TelegramEventTypeEnum::MESSAGE_REACTION, $found->telegramEventType);
    }

    public function test_use_case_persists_channel(): void
    {
        $response = app(HandleWebhookUseCase::class)->execute(new HandleWebhookInput(
            telegramEventType: TelegramEventTypeEnum::MESSAGE_REACTION,
            chatId: self::CHAT_ID,
            chatName: 'Посоны на апщении',
        ));

        $this->assertGreaterThan(0, $response->channelId);
        $this->assertSame(self::CHAT_ID, $response->chatId);
        $this->assertDatabaseCount('telegram_channels', 1);
    }
}