<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge\Tests\Support;

use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;
use Rasuvaeff\Yii3McpAuditLogBridge\AuditActorResolverInterface;
use RuntimeException;

final readonly class ThrowingActorResolver implements AuditActorResolverInterface
{
    #[\Override]
    public function resolve(ToolCallContext $context, ?string $sessionId, ?string $clientName): AuditActor
    {
        throw new RuntimeException('identity backend down');
    }
}
