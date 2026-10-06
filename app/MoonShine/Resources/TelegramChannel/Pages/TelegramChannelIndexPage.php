<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TelegramChannel\Pages;

use App\Application\UseCase\Telegram\SendMessage\SendMessageInput;
use App\Application\UseCase\Telegram\SendMessage\SendMessageUseCase;
use App\Domain\Enum\TelegramEventTypeEnum;
use App\MoonShine\Resources\TelegramChannel\TelegramChannelResource;
use App\Models\TelegramChannel;
use Illuminate\Http\Request;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends IndexPage<TelegramChannelResource>
 */
final class TelegramChannelIndexPage extends IndexPage
{
    /**
     * Текст сообщения ограничен так же, как в Bot API: длиннее телеграмм не примет.
     */
    public const int TEXT_LIMIT = 4096;

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

    protected function buttons(): ListOf
    {
        return parent::buttons()->prepend(
            ActionButton::make('Отправить сообщение')
                ->icon('paper-airplane')
                ->method('sendMessage')
                ->withConfirm(
                    title: static fn (TelegramChannel $channel): string => 'Сообщение в «' . $channel->chat_name . '»',
                    content: '',
                    button: 'Отправить',
                    fields: [
                        Textarea::make('Текст', 'text')->required(),
                    ],
                )
                ->primary()
        );
    }

    /**
     * Что отправлять, страница решает сама: наружу уходит только идентификатор записи,
     * chat_id приложение берёт из базы. Логика отправки целиком в SendMessageUseCase.
     *
     * Два объекта запроса здесь не случайно: MoonShineRequest в контейнере не
     * зарегистрирован, и внедрённый по имени класса он приходит пустым. Тело запроса
     * берём у настоящего Illuminate\Http\Request, а идентификатор строки — у контракта
     * MoonShine, который привязан к запросу правильно.
     */
    #[AsyncMethod]
    public function sendMessage(
        Request $request,
        CrudRequestContract $moonShineRequest,
        SendMessageUseCase $useCase,
    ): void {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:' . self::TEXT_LIMIT],
        ]);

        $response = $useCase->execute(new SendMessageInput(
            channelId: (int) $moonShineRequest->getItemID(),
            text: $data['text'],
        ));

        toast('Сообщение отправлено в «' . $response->chatName . '»', ToastType::SUCCESS);
    }

    /**
     * @param  TableBuilder  $component
     */
    protected function modifyListComponent(ComponentContract $component): TableBuilder
    {
        return $component->columnSelection();
    }
}