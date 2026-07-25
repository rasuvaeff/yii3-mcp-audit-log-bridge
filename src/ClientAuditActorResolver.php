<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge;

use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;

/**
 * Default resolver: the actor is the MCP connection — id = session id,
 * name = the client from the initialize handshake. Correct for servers
 * whose endpoint is not tied to an end user (a single machine agent, a
 * stdio server); for authenticated endpoints implement
 * {@see AuditActorResolverInterface} against the application's identity.
 *
 * @api
 */
final readonly class ClientAuditActorResolver implements AuditActorResolverInterface
{
    public function __construct(
        private string $actorType = 'mcp-client',
    ) {}

    #[\Override]
    public function resolve(ToolCallContext $context, ?string $sessionId, ?string $clientName): AuditActor
    {
        return new AuditActor(type: $this->actorType, id: $sessionId, name: $clientName);
    }
}
