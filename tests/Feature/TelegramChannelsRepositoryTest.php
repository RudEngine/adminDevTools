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

    private function entity(
        string $chatName = 'Посоны на апщении',
        TelegramEventTypeEnum $eventType = TelegramEventTypeEnum::MESSAGE_REACTION,
    ): TelegramChannels {
        return new TelegramChannels(
            telegramEventType: $eventType,
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

    /**
     * Тип события описывает не канал, а повод, по которому он впервые попал в базу,
     * и перезаписывать его последующими событиями незачем.
     */
    public function test_save_keeps_event_type_of_the_first_save(): void
    {
        $this->repository()->save($this->entity());

        $updated = $this->repository()->save(
            $this->entity('Переименованный чат', TelegramEventTypeEnum::CHANNEL_POST),
        );

        $this->assertSame(TelegramEventTypeEnum::MESSAGE_REACTION, $updated->telegramEventType);
        $this->assertDatabaseHas('telegram_channels', [
            'chat_id' => self::CHAT_ID,
            'chat_name' => 'Переименованный чат',
            'last_event_type' => TelegramEventTypeEnum::MESSAGE_REACTION->value,
        ]);
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

    public function test_find_by_id(): void
    {
        $saved = $this->repository()->save($this->entity());

        $found = $this->repository()->findById((int) $saved->id);

        $this->assertNotNull($found);
        $this->assertSame($saved->id, $found->id);
        $this->assertSame(self::CHAT_ID, $found->chatId);
        $this->assertSame('Посоны на апщении', $found->chatName);
    }

    public function test_find_by_id_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->repository()->findById(404));
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