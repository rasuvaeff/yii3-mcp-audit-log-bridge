# rasuvaeff/yii3-mcp-audit-log-bridge

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-mcp-audit-log-bridge?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/yii3-mcp-audit-log-bridge)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-mcp-audit-log-bridge)](https://packagist.org/packages/rasuvaeff/yii3-mcp-audit-log-bridge)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-mcp-audit-log-bridge/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-mcp-audit-log-bridge/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-mcp-audit-log-bridge/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-mcp-audit-log-bridge/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/yii3-mcp-audit-log-bridge/blob/master/psalm.xml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-mcp-audit-log-bridge/php)](https://packagist.org/packages/rasuvaeff/yii3-mcp-audit-log-bridge)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-mcp-audit-log-bridge)](LICENSE.md)
[Русская версия](README.ru.md)

AI audit trail for MCP servers: records every
[rasuvaeff/yii3-mcp](https://github.com/rasuvaeff/yii3-mcp) `tools/call` into
[rasuvaeff/yii3-audit-log](https://github.com/rasuvaeff/yii3-audit-log) — the
answer to "what did the AI actually do in our system".

> **Using an AI coding assistant?** [llms.txt](llms.txt) contains a compact
> API reference you can share with the model. Contributors: see [AGENTS.md](AGENTS.md).

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.3 – 8.5 |
| `rasuvaeff/yii3-mcp` | `^1.1` |
| `rasuvaeff/yii3-audit-log` | `^1.0` |

## Installation

```bash
composer require rasuvaeff/yii3-mcp-audit-log-bridge
```

## Usage

One params line — the interceptor is resolved through the DI container
(`AuditLogger` must be wired, which yii3-audit-log's config does):

```php
// config/params.php
use Rasuvaeff\Yii3McpAuditLogBridge\AuditTrailInterceptor;

'rasuvaeff/yii3-mcp' => [
    'interceptors' => [AuditTrailInterceptor::class],
],
```

Every `tools/call` — attribute tools, OpenAPI-bridged operations,
configurator-registered handlers — produces one audit event:

| Audit field | Value |
|---|---|
| actor | type `mcp-client` (configurable), id = MCP session id, name = client from the initialize handshake (`claude-code 1.2`) |
| action | `mcp.tools.call` |
| subject | type `mcp-tool` (configurable), id = tool name |
| changes | one field per tool argument + `mcp.outcome` (`success`/`rejected`/`error`), `mcp.duration_ms`, `mcp.error` (message, on failure) |
| metadata | requestId = session id, userAgent = client name |

`mcp.outcome` follows yii3-mcp's shared `CallOutcome` vocabulary:
`rejected` marks a client-visible refusal (rate limit, RBAC, session
budget — a thrown `ToolCallException`), `error` an unexpected failure —
so policy rejections are distinguishable from crashes in audit queries.
Failures are recorded and **rethrown** — the MCP error envelope the agent
sees is unchanged, and a call that fails is still audited.

### Masking sensitive arguments

Each tool argument becomes its own change field, so the `AuditLogger`'s
`SensitiveValueMasker` applies to arguments exactly as to any other audited
values: an argument named `password`, `secret`, `token`, `api_key` or
`credit_card` (or your custom key list) is stored as `***`. Call metadata
fields are prefixed with `mcp.` to never collide with argument names.

### Manual wiring

```php
$interceptor = new AuditTrailInterceptor(
    auditLogger: $auditLogger,     // Rasuvaeff\Yii3AuditLog\AuditLogger
    actorType: 'mcp-client',       // default
    subjectType: 'mcp-tool',       // default
);

$server = $factory->create($tools, $configurators, [$interceptor]);
```

## Security

- Arguments are masked by **field name only** (the masker is not recursive):
  a secret nested inside an array argument value is stored as-is. Keep
  secrets in top-level arguments or extend the masker key list.
- The audit event contains tool arguments — treat the audit storage with the
  same care as the data the tools access.
- The interceptor adds no failure mode to tool execution: audit-write errors
  propagate (fail-loud), and tool exceptions are rethrown unchanged.

## Examples

See [examples/](examples/) — runs offline.

| Script | Shows | Needs server? |
|--------|-------|:-------------:|
| [`audit-trail.php`](examples/audit-trail.php) | A tool call recorded into an in-memory audit log, with a masked `password` argument | no |

## Development

No PHP/Composer on the host — run in Docker via the `composer:2` image:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Or with Make: `make build`, `make cs-fix`, `make psalm`, `make test`.

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
