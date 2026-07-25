# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.1 — 2026-07-25

- Document the exact Composer Dependency Analyser exclusion required when this
  package is consumed only through yiisoft/config metadata.

## 2.0.0 — 2026-07-25

- **BREAKING.** `AuditTrailInterceptor`'s second constructor argument is now
  `AuditActorResolverInterface $actorResolver` instead of
  `string $actorType`. Manual wiring passes
  `new ClientAuditActorResolver($actorType)` for identical behavior; wiring by
  FQCN through params/DI is unaffected — the argument defaults to
  `new ClientAuditActorResolver()`.
- The audit actor is now an extension point: `AuditActorResolverInterface`
  decides who a `tools/call` is credited to. `ClientAuditActorResolver`
  (default) keeps the 1.x behavior — the MCP connection, id = session id.
  `IdentityAuditActorResolver` credits the authenticated user
  (`mcp-user` + user id, guests fall back to the connection), taking the
  identity from `rasuvaeff/yii3-mcp-rbac-bridge`'s `IdentitySourceInterface`
  (a `suggest`, not a hard dependency). Without it the journal cannot answer
  "which user did what": session ids expire with the session store while
  audit rows live for years.
- Every event now records the connection as change fields — `mcp.session`,
  `mcp.client` and `mcp.client_id` (the endpoint-secret client identity from
  yii3-mcp, previously never audited) — so crediting the user loses nothing.
  `mcp.client_id` is omitted when the transport carries none (e.g. stdio).
- Docs: `README.ru.md` rewritten as a faithful mirror of `README.md` — the
  bulk-localized version carried machine-translation artifacts (`@@ЛИНИЯ@@`
  markers, a mangled package name, "введите `mcp-client`" for "type
  `mcp-client`", Security bullets rendered as em-dashes).

## 1.1.0 — 2026-07-24

- `mcp.outcome` adopts yii3-mcp's shared `CallOutcome` vocabulary: a
  client-visible refusal (`ToolCallException` — rate limit, RBAC, session
  budget) is now recorded as `rejected` instead of `error`; unexpected
  failures stay `error`. Requires `rasuvaeff/yii3-mcp` `^1.6`.
- Internal: benchmark migrated to testo/bench comparison style (`#[Bench]`
  without `callables` aborts on testo/bench 0.1.6); measures interceptor
  overhead against a bare tool handler.

## 1.0.0 — 2026-07-24

- Initial release: `AuditTrailInterceptor` records every MCP `tools/call`
  (actor from the initialize handshake, tool name as subject, masked
  arguments, outcome, duration) into `rasuvaeff/yii3-audit-log`.
