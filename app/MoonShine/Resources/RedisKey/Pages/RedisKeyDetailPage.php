<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\RedisKey\Pages;

use App\MoonShine\Resources\RedisKey\RedisKeyResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends DetailPage<RedisKeyResource>
 */
final class RedisKeyDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Text::make('Соединение', 'connection'),

            Text::make('Ключ', 'key'),

            Text::make('Тип', 'type')->badge(Color::PURPLE),

            Text::make('TTL', 'ttl'),

            Textarea::make('Значение', 'value'),
        ];
    }

    /**
     * Ключ мог истечь или не существовать вовсе — тогда страницы просто нет.
     * Без этой проверки MoonShine отрисовал бы пустую карточку с кнопкой удаления.
     *
     * @return iterable<mixed>
     */
    protected function components(): iterable
    {
        abort_if(!$this->getResource()->isItemExists(), 404);

        return parent::components();
    }
}
