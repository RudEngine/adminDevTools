<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TelegramChannel;

use App\Models\TelegramChannel;
use App\MoonShine\Resources\TelegramChannel\Pages\TelegramChannelFormPage;
use App\MoonShine\Resources\TelegramChannel\Pages\TelegramChannelIndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * @extends ModelResource<TelegramChannel, TelegramChannelIndexPage, TelegramChannelFormPage, null>
 */
#[Icon('chat-bubble-left-right')]
#[Order(2)]
class TelegramChannelResource extends ModelResource
{
    protected string $model = TelegramChannel::class;

    protected string $column = 'chat_name';

    protected bool $simplePaginate = true;

    public function getTitle(): string
    {
        return 'Телеграмм каналы';
    }

    protected function activeActions(): ListOf
    {
        // Каналы появляются из вебхука, руками их не создают.
        return parent::activeActions()->except(Action::VIEW, Action::CREATE);
    }

    protected function pages(): array
    {
        return [
            TelegramChannelIndexPage::class,
            TelegramChannelFormPage::class,
        ];
    }

    protected function search(): array
    {
        return [
            'id',
            'chat_id',
            'chat_name',
        ];
    }
}
