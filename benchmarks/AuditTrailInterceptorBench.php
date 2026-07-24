<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge\Benchmarks;

use Rasuvaeff\Yii3AuditLog\AuditLogger;
use Rasuvaeff\Yii3AuditLog\NullAuditWriter;
use Rasuvaeff\Yii3AuditLog\SensitiveValueMasker;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;
use Rasuvaeff\Yii3McpAuditLogBridge\AuditTrailInterceptor;
use Rasuvaeff\Yii3McpAuditLogBridge\Benchmarks\Support\SystemClock;
use Testo\Bench;

/**
 * Measures the per-call overhead the audit interceptor adds on top of a bare
 * tool handler (NullAuditWriter, so only the bridge's own work — change-set
 * building, masking, clock reads — is on the clock).
 */
final class AuditTrailInterceptorBench
{
    private AuditTrailInterceptor $interceptor;

    private ToolCallContext $context;

    public function __construct()
    {
        $this->interceptor = new AuditTrailInterceptor(new AuditLogger(
            writer: new NullAuditWriter(),
            clock: new SystemClock(),
            masker: new SensitiveValueMasker(),
        ));
        $this->context = new ToolCallContext(
            toolName: 'order.status',
            arguments: ['orderId' => '42', 'password' => 'p@ss', 'locale' => 'en'],
        );
    }

    #[Bench(
        callables: [
            'bare handler' => [self::class, 'bareToolCall'],
        ],
        calls: 100_000,
        iterations: 5,
    )]
    public function interceptSuccessfulCall(): void
    {
        $this->interceptor->intercept($this->context, static fn (): string => 'paid');
    }

    public static function bareToolCall(): string
    {
        return 'paid';
    }
}
