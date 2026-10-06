<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\RedisKey\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use MoonShine\Contracts\Core\Paginator\PaginatorContract;
use MoonShine\Contracts\Core\TypeCasts\DataCasterContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Core\TypeCasts\MixedDataWrapper;
use MoonShine\Crud\TypeCasts\PaginatorCaster;

/**
 * Кастер для элементов-массивов.
 *
 * Готовый MixedDataCaster подошёл бы, если бы не пагинация: его paginatorCast()
 * всегда возвращает null, из-за чего таблица получала объект пагинатора вместо
 * списка и рисовала его внутренние свойства как строки.
 */
final readonly class RedisKeyCaster implements DataCasterContract
{
    public function __construct(
        private string $keyName = 'id',
    ) {
    }

    public function cast(mixed $data): DataWrapperContract
    {
        /** @var null|string|int $key */
        $key = data_get($data, $this->keyName);

        return new MixedDataWrapper($data, $key);
    }

    public function paginatorCast(mixed $data): ?PaginatorContract
    {
        if (!$data instanceof Paginator && !$data instanceof CursorPaginator) {
            return null;
        }

        $pageName = method_exists($data, 'getPageName') ? $data->getPageName() : 'page';

        return (new PaginatorCaster(
            $data->toArray(),
            $data->items(),
            pageName: $pageName,
        ))->cast();
    }
}
