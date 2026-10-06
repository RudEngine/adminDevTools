<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TelegramChannel\Pages;

use App\Domain\Enum\TelegramEventTypeEnum;
use App\MoonShine\Resources\TelegramChannel\TelegramChannelResource;
use App\Models\TelegramChannel;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<TelegramChannelResource, TelegramChannel>
 */
final class TelegramChannelFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),

                // chat_id приходит от телеграмм — показываем, но не даём переписать.
                Number::make('ID чата', 'chat_id')->readonly(),

                Text::make('Название', 'chat_name')->required(),

                Enum::make('Первое событие', 'last_event_type')
                    ->attach(TelegramEventTypeEnum::class)
                    ->required(),

                Date::make('Добавлен', 'created_at')
                    ->format('d.m.Y H:i')
                    ->readonly(),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'chat_name' => ['required', 'string', 'max:255'],
            'last_event_type' => ['required', Rule::enum(TelegramEventTypeEnum::class)],
        ];
    }
}