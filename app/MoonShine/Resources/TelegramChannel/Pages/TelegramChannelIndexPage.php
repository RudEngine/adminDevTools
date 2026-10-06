<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TelegramChannel\Pages;

use App\Domain\Enum\TelegramEventTypeEnum;
use App\MoonShine\Resources\TelegramChannel\TelegramChannelResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<TelegramChannelResource>
 */
final class TelegramChannelIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),

            Number::make('ID чата', 'chat_id')->sortable(),

            Text::make('Название', 'chat_name')->sortable(),

            Enum::make('Первое событие', 'last_event_type')
                ->attach(TelegramEventTypeEnum::class)
                ->badge(Color::PURPLE),

            Date::make('Обновлён', 'updated_at')
                ->format('d.m.Y H:i')
                ->sortable(),
        ];
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Название', 'chat_name'),

            Enum::make('Первое событие', 'last_event_type')
                ->attach(TelegramEventTypeEnum::class),
        ];
    }

    /**
     * @param  TableBuilder  $component
     */
    protected function modifyListComponent(ComponentContract $component): TableBuilder
    {
        return $component->columnSelection();
    }
}