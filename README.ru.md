# rasuvaeff/yii3-mcp-audit-log-bridge
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-mcp-audit-log-bridge?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/yii3-mcp-audit-log-bridge)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-mcp-audit-log-bridge)](https://packagist.org/packages/rasuvaeff/yii3-mcp-audit-log-bridge)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-mcp-audit-log-bridge/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-mcp-audit-log-bridge/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-mcp-audit-log-bridge/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-mcp-audit-log-bridge/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/yii3-mcp-audit-log-bridge/blob/master/psalm.xml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-mcp-audit-log-bridge/php)](https://packagist.org/packages/rasuvaeff/yii3-mcp-audit-log-bridge)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-mcp-audit-log-bridge)](LICENSE.md)
Контрольный журнал AI для серверов MCP: записывает все
[rasuvaeff/yii3-mcp](https://github.com/rasuvaeff/yii3-mcp) `tools/call` into
[rasuvaeff/yii3-audit-log](https://github.com/rasuvaeff/yii3-audit-log) — the
ответ на вопрос «что на самом деле делал ИИ в нашей системе».

 > **Используете помощника по кодированию с использованием искусственного интеллекта?** [llms.txt](llms.txt) содержит компактную ссылку
 > API, которой вы можете поделиться с моделью. Авторы: см. [AGENTS.md](AGENTS.md). @@ЛИНИЯ@@
## Требования
| Требование | Версия |
 |-------------|---------|
 | PHP | 8,3 – 8,5 |
 | `rasuvaeff/yii3-mcp` | `^1.6` |
 | `rasuvaeff/yii3-audit-log` | `^1.0` |
 | `rasuvaeff/yii3-mcp-rbac-bridge` | `^1.0`, опционально — только для `IdentityAuditActorResolver` |
## Установка
```bash
composer require rasuvaeff/yii3-mcp-audit-log-bridge
```
## Использование
Одна строка параметров — перехватчик разрешается через DI-контейнер
 (должен быть подключен `AuditLogger`, что и делает конфигурация yii3-audit-log):

```php
// config/params.php
use Rasuvaeff\Yii3McpAuditLogBridge\AuditTrailInterceptor;

'rasuvaeff/yii3-mcp' => [
    'interceptors' => [AuditTrailInterceptor::class],
],
```
Каждый `tools/call` (инструменты атрибутов, операции с мостом OpenAPI,
 обработчики, зарегистрированные в конфигураторе) создает одно событие аудита:

 | Поле аудита | Значение |
 |---|---|
 | actor | определяется через `AuditActorResolverInterface`; по умолчанию тип `mcp-client`, id = идентификатор MCP-сессии, name = клиент из initialize-хендшейка (`claude-code 1.2`) |
 | действие | `mcp.tools.call` |
 | предмет | тип `mcp-tool` (настраиваемый), id = имя инструмента |
 | изменения | одно поле на каждый аргумент инструмента + `mcp.outcome` (`success`/`rejected`/`error`), `mcp.duration_ms`, `mcp.session`, `mcp.client`, `mcp.client_id` (если транспорт его несёт), `mcp.error` (сообщение, при сбое) |
 | метаданные | requestId = идентификатор сеанса, userAgent = имя клиента |

`mcp.outcome` следует единому словарю `CallOutcome` из yii3-mcp:
`rejected` — видимый клиенту отказ (rate limit, RBAC, session budget —
брошенный `ToolCallException`), `error` — неожиданный сбой; отказы политик
отличимы от падений в audit-запросах. Сбои записываются и
**перебрасываются** — MCP error envelope, который видит агент, не меняется,
а упавший вызов всё равно попадает в аудит.
### Кто такой actor: подключение или пользователь

По умолчанию actor — это MCP-**подключение**: id сессии плюс имя клиента из
хендшейка. Для сервера с одним агентом этого достаточно, но на вопрос «какой
пользователь что сделал» так не ответить: идентификаторы сессий умирают вместе
с TTL хранилища, а записи аудита живут годами.

На аутентифицированном эндпоинте свяжите `AuditActorResolverInterface`:

| Резолвер | Actor |
|---|---|
| `ClientAuditActorResolver` (по умолчанию) | тип `mcp-client`, id = id сессии, name = клиент из хендшейка |
| `IdentityAuditActorResolver` | тип `mcp-user`, id = id аутентифицированного пользователя (гость → откат к подключению) |
| собственный | что угодно, что известно приложению |

`IdentityAuditActorResolver` берёт идентичность из `IdentitySourceInterface`
пакета
[rasuvaeff/yii3-mcp-rbac-bridge](https://github.com/rasuvaeff/yii3-mcp-rbac-bridge)
— того же источника, которым пользуются его RBAC- и session-binding-интерцепторы,
поэтому аудит и решение о доступе не могут разойтись в том, кто вызывает.
Пакет указан в `suggest`, а не в жёстких зависимостях; установить его и
связать резолвер — одна строка:

```php
// config/common/di/mcp.php
use Rasuvaeff\Yii3McpAuditLogBridge\AuditActorResolverInterface;
use Rasuvaeff\Yii3McpAuditLogBridge\IdentityAuditActorResolver;

return [
    AuditActorResolverInterface::class => IdentityAuditActorResolver::class,
];
```

Без rbac-bridge реализуйте интерфейс поверх той идентичности, что уже есть
в приложении:

```php
final readonly class CurrentUserActorResolver implements AuditActorResolverInterface
{
    public function __construct(private CurrentUser $currentUser) {}

    public function resolve(ToolCallContext $context, ?string $sessionId, ?string $clientName): AuditActor
    {
        return $this->currentUser->isGuest()
            ? new AuditActor(type: 'mcp-client', id: $sessionId, name: $clientName)
            : new AuditActor(type: 'mcp-user', id: $this->currentUser->getId(), name: $clientName);
    }
}
```

Связь с подключением не теряется, когда actor становится пользователем:
`mcp.session`, `mcp.client` и `mcp.client_id` (идентичность клиента из
endpoint-секрета yii3-mcp — её, в отличие от имени из хендшейка, клиент не
может подделать) пишутся в changes при каждом вызове.

Резолвер, бросающий исключение, роняет вызов — событие не будет записано под
неправильным actor'ом.

### Маскировка деликатных аргументов
Каждый аргумент инструмента становится собственным полем изменения, поэтому
 `SensitiveValueMasker` `AuditLogger` применяется к аргументам точно так же, как и к любым другим проверяемым значениям
: аргумент с именем `password`, `secret`, `token`, `api_key` или
 `credit_card` (или ваш собственный список ключей) сохраняется как `***`. Поля метаданных вызова
 имеют префикс `mcp.`, чтобы никогда не конфликтовать с именами аргументов. @@ЛИНИЯ@@
### Ручная проводка
```php
$interceptor = new AuditTrailInterceptor(
    auditLogger: $auditLogger,                            // Rasuvaeff\Yii3AuditLog\AuditLogger
    actorResolver: new ClientAuditActorResolver('agent'), // по умолчанию: ClientAuditActorResolver('mcp-client')
    subjectType: 'mcp-tool',                              // по умолчанию
);

$server = $factory->create($tools, $configurators, [$interceptor]);
```

Переход с 1.x: вторым аргументом конструктора был `string $actorType`.
Передавайте вместо него `new ClientAuditActorResolver($actorType)` — поведение
то же, проводка через params/DI по FQCN не меняется.
## Безопасность
- Аргументы маскируются **только именем поля** (маскировщик не является рекурсивным):
 секрет, вложенный в значение аргумента массива, сохраняется как есть. Сохраняйте секреты
 в аргументах верхнего уровня или расширяйте список ключей маскера.
 — событие аудита содержит аргументы инструмента — относитесь к хранилищу аудита с
 так же осторожно, как и к данным, к которым получают доступ инструменты.
 — перехватчик не добавляет режим сбоя к выполнению инструмента: ошибки аудита-записи
 распространяются (сбой-громкий), а исключения инструмента повторно выдаются без изменений. @@ЛИНИЯ@@
## Примеры
См. [examples/](examples/) — работает в автономном режиме.

 | Скрипт | Шоу | Нужен сервер? |
 |--------|-------|:-------------:|
 | [`audit-trail.php`](examples/audit-trail.php) | Вызов инструмента записывается в журнал аудита в памяти с замаскированным аргументом `password` | нет |
 | [`user-actor.php`](examples/user-actor.php) | Тот же вызов, записанный на аутентифицированного пользователя вместо подключения, плюс откат для гостя | нет |
## Разработка
На хосте нет PHP/Composer — запустите в Docker через образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```
Или с помощью Make: make build, make cs-fix, make psalm, make test. @@ЛИНИЯ@@
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
