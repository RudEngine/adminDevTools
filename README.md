# Admin

Админка на MoonShine (Laravel). Деплой — Dokploy, сборка Railpack (FrankenPHP + Caddy).

> ⚠️ Никаких паролей и хешей в этом файле и в репозитории. Только в Dokploy (Environment) и менеджере паролей.

## Защита `/admin` на уровне Caddy

Перед админкой стоит второй замок — HTTP Basic Auth в Caddy (`Caddyfile` в корне, Railpack подхватывает его вместо стандартного).

Как работает:

1. Запрос на `/admin` или `/admin/*` основного домена (`APP_DOMAIN`) сначала проверяет Caddy.
2. Нет правильного логина/пароля → `401` сразу, PHP и Laravel не запускаются. Сканеры и боты отсекаются здесь.
3. Пароль верный → запрос идёт в Laravel, дальше обычный логин MoonShine (email + пароль).

Итого при входе в админку два шага: окно браузера (basic auth), затем форма MoonShine. Браузер помнит basic auth до перезапуска.

Не закрыто паролем (так и задумано):

- `/up` — healthcheck Dokploy;
- `/vendor/moonshine/*` — ассеты админки;
- api-домен (`APP_API_DOMAIN`) — там `/admin` нет, отдаётся JSON 404.

Также в `Caddyfile`: логи Caddy только уровня WARN и выше (без access-логов).

### Переменные окружения (Dokploy → Environment)

| Переменная         | Что это                                        |
|--------------------|------------------------------------------------|
| `APP_DOMAIN`       | основной домен, на нём действует basic auth    |
| `ADMIN_BASIC_USER` | логин basic auth                               |
| `ADMIN_BASIC_HASH` | bcrypt-хеш пароля (не сам пароль!)             |

Если Dokploy портит значение с `$` — взять его в одинарные кавычки или заменить каждый `$` на `$$`.

### Сменить пароль

```sh
export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
docker run --rm caddy caddy hash-password --cost 10 --plaintext 'новый-пароль'
```

Хеш → `ADMIN_BASIC_HASH` в Dokploy → Redeploy. Коммит не нужен.

### Важно

- Путь в `Caddyfile` должен совпадать с префиксом MoonShine (`MOONSHINE_ROUTE_PREFIX`, по умолчанию `admin`). Сменили префикс — поправьте `Caddyfile`, иначе админка останется без basic auth.
- Откат: удалить `Caddyfile` и задеплоить — Railpack вернёт стандартный конфиг.

### Проверка после деплоя

```sh
curl -I https://<домен>/up                          # 200
curl -I https://<домен>/admin                       # 401
curl -I -u <логин>:<пароль> https://<домен>/admin   # 302 на /admin/login
curl -I https://<домен>/vendor/moonshine/logo-app.svg   # 200
curl -I https://api.<домен>/admin                   # 404 JSON
```
