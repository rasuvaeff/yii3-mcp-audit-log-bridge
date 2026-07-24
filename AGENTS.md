# AGENTS.md — yii3-mcp-audit-log-bridge

Guidance for AI agents working on this package. Read before changing code.

## What this is

Bridge between `rasuvaeff/yii3-mcp` and `rasuvaeff/yii3-audit-log`
(namespace `Rasuvaeff\Yii3McpAuditLogBridge`): `AuditTrailInterceptor`
implements yii3-mcp's `ToolCallInterceptorInterface` and records every MCP
`tools/call` as one audit event — actor from the session's initialize
handshake, subject = tool name, each argument as its own change field
(masked by field name via the AuditLogger's `SensitiveValueMasker`), plus
`mcp.outcome` / `mcp.duration_ms` / `mcp.error`.

Public API: `AuditTrailInterceptor` (that's it — one class).

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Never swallow or reshape tool failures.** The interceptor records the
   error and rethrows the ORIGINAL exception; the MCP error envelope the
   agent sees must be byte-identical with and without this bridge. Audit
   metadata change-fields keep the `mcp.` prefix so they can never collide
   with (or be shadowed by) tool argument names.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make: `make build`, `make cs-fix`, `make psalm`, `make test`,
`make test-coverage`, `make mutation`, `make release-check`.

Both runtime dependencies (`rasuvaeff/yii3-mcp`, `rasuvaeff/yii3-audit-log`)
are on Packagist — a plain `composer install` works, no path repos needed.

## Invariants & gotchas

- Masking is field-name based and NOT recursive: only top-level arguments
  named like the masker keys are masked. Document, don't "fix" silently —
  recursive masking belongs to yii3-audit-log.
- `mcp.duration_ms` is measured with `hrtime()` around `$next()` including
  downstream interceptors — it is the wall time of the wrapped chain.
- The audit event is written on BOTH success and failure paths; a failing
  audit write propagates (fail-loud, no try/catch around the logger).
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.
- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment
  (e.g. `actions/checkout@<sha> # v4`). Never revert to floating `@vN` tags.
  Updates go through Dependabot, which bumps the SHA and preserves the comment.
  Workflows also carry `permissions: { contents: read }` at workflow level and
  `persist-credentials: false` on every `actions/checkout` step. Verify with
  `zizmor --persona=auditor .github/` — must report no `unpinned-uses`,
  `excessive-permissions`, or `artipacked` findings.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; if the change affects public API or release safety,
  also run `make release-check`. Paste the output.
