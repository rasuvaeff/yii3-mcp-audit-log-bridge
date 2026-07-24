# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
