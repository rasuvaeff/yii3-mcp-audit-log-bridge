# Upgrade guide

## 2.3.x → 3.0.0

Upgrade `rasuvaeff/yii3-mcp` to `^6.0` in the same step:

```bash
composer require rasuvaeff/yii3-mcp:^6.0 rasuvaeff/yii3-mcp-audit-log-bridge:^3.0
```

Code that names the interceptor types follows the `yii3-mcp` upgrade guide:
`Rasuvaeff\Yii3Mcp\Interceptor\*` is now `Rasuvaeff\Mcp\Interceptor\*`, from
`rasuvaeff/mcp`, which such code should require directly. The
bridge's own class names, behaviour and configuration are unchanged, but their signatures use the new
types: an `AuditActorResolverInterface` implementation must accept
`Rasuvaeff\Mcp\Interceptor\ToolCallContext`.
