<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\RedisKey;

use App\Domain\DevTools\Redis\RedisKeyBrowserInterface;
use App\Domain\DevTools\Redis\RedisKeyInfo;
use App\Domain\DevTools\Redis\RedisKeyPage;
use App\MoonShine\Resources\RedisKey\Pages\RedisKeyDetailPage;
use App\MoonShine\Resources\RedisKey\Pages\RedisKeyIndexPage;
use App\MoonShine\Resources\RedisKey\Support\RedisKeyCaster;
use App\MoonShine\Resources\RedisKey\Support\RedisKeyId;
use App\MoonShine\Resources\RedisKey\Support\RedisValueFormatter;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator as BasePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use LogicException;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataCasterContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\Laravel\Collections\Fields;
use MoonShine\Laravel\DependencyInjection\MoonShine;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * Просмотр ключей Redis.
 *
 * Это не ModelResource: за данными стоит не таблица, а живой Redis, поэтому
 * элементы — простые массивы, а идентификатор строки собирается из соединения
 * и имени ключа (см. RedisKeyId).
 *
 * @extends CrudResource<MoonShine, array<string, mixed>, RedisKeyIndexPage, null, RedisKeyDetailPage, InvalidArgumentException, Fields>
 */
#[Icon('circle-stack')]
#[Order(3)]
class RedisKeyResource extends CrudResource
{
    protected ?string $casterKeyName = 'id';

    protected string $column = 'key';

    protected int $itemsPerPage = 50;

    /**
     * Результаты обходов за текущий запрос, по ключу «соединение|маска|предел».
     *
     * @var array<string, RedisKeyPage>
     */
    private array $scanned = [];

    public function __construct(
        CoreContract $core,
        private readonly RedisKeyBrowserInterface $browser,
    ) {
        parent::__construct($core);
    }

    public function getTitle(): string
    {
        return 'Redis';
    }

    /**
     * MixedDataCaster по умолчанию не умеет пагинацию — подменяем на свой.
     */
    public function getCaster(): DataCasterContract
    {
        return new RedisKeyCaster((string) $this->casterKeyName);
    }

    protected function pages(): array
    {
        return [
            RedisKeyIndexPage::class,
            RedisKeyDetailPage::class,
        ];
    }

    /**
     * Создавать и править ключи из админки нельзя: инструмент нужен, чтобы
     * посмотреть и при необходимости удалить. Массовое удаление тоже выключено —
     * слишком легко снести лишнее одним кликом.
     */
    protected function activeActions(): ListOf
    {
        return parent::activeActions()->only(Action::VIEW, Action::DELETE);
    }

    /**
     * Поиск по ключам делает маска, отдельная строка поиска только путала бы.
     */
    protected function search(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function getConnections(): array
    {
        return $this->browser->connections();
    }

    public function getConnection(): string
    {
        $value = $this->getFilterParams()['connection'] ?? null;

        if (is_string($value) && $value !== '' && in_array($value, $this->getConnections(), true)) {
            return $value;
        }

        return $this->getConnections()[0] ?? 'default';
    }

    public function getPattern(): string
    {
        $value = $this->getFilterParams()['pattern'] ?? null;

        return is_string($value) && $value !== '' ? $value : '*';
    }

    /**
     * Префикс, который Laravel дописывает ко всем своим ключам. Сами ключи
     * показываются вместе с ним, поэтому без подсказки маску не составить.
     */
    public function getKeyPrefix(): string
    {
        return (string) config('database.redis.options.prefix');
    }

    public function getScanLimit(): int
    {
        return max(1, (int) config('devtools.redis.scan_limit', 1000));
    }

    /**
     * Ключей нашлось больше, чем разрешено собрать за обход.
     */
    public function isTruncated(): bool
    {
        return $this->scan()->truncated;
    }

    public function getItems(): iterable|Collection|LazyCollection|CursorPaginator|Paginator
    {
        $connection = $this->getConnection();

        $rows = array_map(
            fn (RedisKeyInfo $info): array => $this->row($connection, $info),
            $this->scan()->keys,
        );

        if (!$this->isPaginationUsed()) {
            return $rows;
        }

        $perPage = $this->getItemsPerPage();
        $page = max(1, $this->getPaginatorPage());

        // Redis не умеет отдавать «страницу» обхода, поэтому режем уже собранное.
        $paginator = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            [
                'path' => BasePaginator::resolveCurrentPath(),
                'pageName' => $this->getQueryParamName('page'),
            ],
        );

        return $paginator->appends(
            $this->getQueryParams()->except($this->getQueryParamName('page'))->toArray(),
        );
    }

    public function findItem(bool $orFail = false): ?DataWrapperContract
    {
        $id = RedisKeyId::fromString((string) $this->getItemID());

        // Неизвестное соединение в адресе — такой же промах, как и несуществующий
        // ключ: страница должна ответить «не найдено», а не пятисоткой.
        try {
            $value = $id === null ? null : $this->browser->find($id->connection, $id->key);
        } catch (InvalidArgumentException) {
            $value = null;
        }

        if ($value === null || $id === null) {
            if ($orFail) {
                throw new InvalidArgumentException('Ключ Redis не найден');
            }

            return null;
        }

        return $this->getCaster()->cast([
            'id' => (new RedisKeyId($id->connection, $value->key))->toString(),
            'connection' => $id->connection,
            'key' => $value->key,
            'type' => $value->type,
            'ttl' => $this->ttl($value->ttl),
            'value' => (new RedisValueFormatter)->format($value->value),
        ]);
    }

    public function delete(DataWrapperContract $item, ?FieldsContract $fields = null): bool
    {
        $id = RedisKeyId::fromString((string) $item->getKey());

        if ($id === null) {
            return false;
        }

        return $this->browser->delete($id->connection, $id->key);
    }

    /**
     * @param array<int|string> $ids
     */
    public function massDelete(array $ids): void
    {
        throw new LogicException('Массовое удаление ключей Redis из админки запрещено');
    }

    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        throw new LogicException('Редактирование ключей Redis из админки запрещено');
    }

    /**
     * Обход выполняется один раз за запрос: список и предупреждение о пределе
     * строятся из одного и того же результата.
     */
    private function scan(): RedisKeyPage
    {
        $connection = $this->getConnection();
        $pattern = $this->getPattern();
        $limit = $this->getScanLimit();

        // Ключ кэша включает аргументы: страница успевает спросить про обход до
        // того, как MoonShine положит в ресурс параметры запроса, и закэшированный
        // «безфильтровый» результат тогда подменил бы отфильтрованный.
        $cacheKey = $connection . '|' . $pattern . '|' . $limit;

        return $this->scanned[$cacheKey] ??= $this->browser->keys($connection, $pattern, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $connection, RedisKeyInfo $info): array
    {
        return [
            'id' => (new RedisKeyId($connection, $info->key))->toString(),
            'connection' => $connection,
            'key' => $info->key,
            'type' => $info->type,
            'ttl' => $this->ttl($info->ttl),
            'size' => $info->size,
        ];
    }

    private function ttl(?int $ttl): string
    {
        return $ttl === null ? '∞' : $ttl . ' с';
    }
}
