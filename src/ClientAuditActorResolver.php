<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge;

use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;

/**
 * Default resolver: the actor is the MCP connection — id = session id (the
 * client id where there is no session: the stateless 2026-07-28 era, stdio),
 * name = the client the call named itself as. Correct for servers
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
        // no session to name the connection (the stateless era, stdio): the
        // client id the endpoint secret resolved is the next best identity
        return new AuditActor(type: $this->actorType, id: $sessionId ?? $context->clientId, name: $clientName);
    }
}
