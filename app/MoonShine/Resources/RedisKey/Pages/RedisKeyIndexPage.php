<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\RedisKey\Pages;

use App\MoonShine\Resources\RedisKey\RedisKeyResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<RedisKeyResource>
 */
final class RedisKeyIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Text::make('Ключ', 'key'),

            Text::make('Тип', 'type')->badge(Color::PURPLE),

            Text::make('TTL', 'ttl'),

            Number::make('Размер', 'size'),
        ];
    }

    protected function filters(): iterable
    {
        $resource = $this->getResource();
        $connections = $resource->getConnections();
        $prefix = $resource->getKeyPrefix();

        return [
            Select::make('Соединение', 'connection')
                ->options(array_combine($connections, $connections))
                ->nullable(),

            Text::make('Маска', 'pattern')
                ->placeholder('*')
                ->hint(
                    $prefix === ''
                        ? 'Синтаксис Redis MATCH, например telegram:*'
                        : 'Синтаксис Redis MATCH. Ключи приложения начинаются с «' . $prefix . '»'
                ),
        ];
    }

    /**
     * Неполная выборка выглядит на странице ровно как полная, поэтому про упор
     * в предел надо сказать словами — иначе отсутствие ключа примут за его отсутствие в Redis.
     *
     * @return list<ComponentContract>
     */
    protected function topLayer(): array
    {
        $resource = $this->getResource();

        return [
            Alert::make('exclamation-triangle', Color::WARNING)
                ->content(
                    'Показаны первые ' . $resource->getScanLimit()
                    . ' ключей — найдено больше, уточните маску.'
                )
                ->canSee(static fn (): bool => $resource->isTruncated()),

            ...parent::topLayer(),
        ];
    }
}
