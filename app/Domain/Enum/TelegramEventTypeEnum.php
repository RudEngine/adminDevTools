<?php
declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Типы обновлений Telegram, из которых можно достать чат.
 *
 * Значения — имена необязательных полей объекта Update (https://core.telegram.org/bots/api#update),
 * порядок сохранён как в документации. Поля Update без чата (business_connection, inline_query,
 * chosen_inline_result, shipping_query, pre_checkout_query, purchased_paid_media, poll,
 * managed_bot, subscription) здесь отсутствуют: канал по ним не создать, в них есть только
 * пользователь или идентификатор опроса/подписки.
 */
enum TelegramEventTypeEnum: string
{
    case MESSAGE = "message";
    case EDITED_MESSAGE = "edited_message";
    case CHANNEL_POST = "channel_post";
    case EDITED_CHANNEL_POST = "edited_channel_post";
    case BUSINESS_MESSAGE = "business_message";
    case EDITED_BUSINESS_MESSAGE = "edited_business_message";
    case DELETED_BUSINESS_MESSAGES = "deleted_business_messages";
    case GUEST_MESSAGE = "guest_message";
    case MESSAGE_REACTION = "message_reaction";
    case MESSAGE_REACTION_COUNT = "message_reaction_count";
    case CALLBACK_QUERY = "callback_query";
    case POLL_ANSWER = "poll_answer";
    case MY_CHAT_MEMBER = "my_chat_member";
    case CHAT_MEMBER = "chat_member";
    case CHAT_JOIN_REQUEST = "chat_join_request";
    case CHAT_BOOST = "chat_boost";
    case REMOVED_CHAT_BOOST = "removed_chat_boost";
    case STOPPED_MESSAGE_GENERATION = "stopped_message_generation";

    /**
     * Путь до объекта Chat внутри полезной нагрузки события.
     *
     * У большинства событий чат лежит прямо в chat, но у callback_query он спрятан
     * в сообщении с кнопкой, а у poll_answer чат есть только у анонимного голосующего.
     *
     * @return list<string>
     */
    public function chatPath(): array
    {
        return match ($this) {
            self::CALLBACK_QUERY => ['message', 'chat'],
            self::POLL_ANSWER => ['voter_chat'],
            default => ['chat'],
        };
    }

    /**
     * Подпись для админки: MoonShine\UI\Fields\Enum сам подхватывает этот метод.
     */
    public function toString(): string
    {
        return match ($this) {
            self::MESSAGE => 'Сообщение',
            self::EDITED_MESSAGE => 'Изменённое сообщение',
            self::CHANNEL_POST => 'Пост в канале',
            self::EDITED_CHANNEL_POST => 'Изменённый пост в канале',
            self::BUSINESS_MESSAGE => 'Сообщение бизнес-аккаунта',
            self::EDITED_BUSINESS_MESSAGE => 'Изменённое сообщение бизнес-аккаунта',
            self::DELETED_BUSINESS_MESSAGES => 'Удалённые сообщения бизнес-аккаунта',
            self::GUEST_MESSAGE => 'Гостевое сообщение',
            self::MESSAGE_REACTION => 'Реакция на сообщение',
            self::MESSAGE_REACTION_COUNT => 'Счётчик анонимных реакций',
            self::CALLBACK_QUERY => 'Нажатие на кнопку',
            self::POLL_ANSWER => 'Голос в опросе',
            self::MY_CHAT_MEMBER => 'Статус бота в чате',
            self::CHAT_MEMBER => 'Статус участника чата',
            self::CHAT_JOIN_REQUEST => 'Заявка на вступление',
            self::CHAT_BOOST => 'Буст чата',
            self::REMOVED_CHAT_BOOST => 'Буст чата снят',
            self::STOPPED_MESSAGE_GENERATION => 'Остановка генерации сообщения',
        };
    }
}