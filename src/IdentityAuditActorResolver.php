<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge;

use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;
use Rasuvaeff\Yii3McpRbacBridge\IdentitySourceInterface;

/**
 * Credits the authenticated user, taking the identity from
 * rasuvaeff/yii3-mcp-rbac-bridge's `IdentitySourceInterface` — the same
 * source its RBAC and session-binding interceptors use, so the audit trail
 * and the access decision can never disagree about who is calling.
 *
 * A guest call (no identity) falls back to the connection: type
 * `mcp-client`, id = session id — exactly what {@see ClientAuditActorResolver}
 * would have written, so a mixed authenticated/anonymous endpoint stays
 * readable.
 *
 * `rasuvaeff/yii3-mcp-rbac-bridge` is a `suggest`, not a hard dependency:
 * install it and bind the resolver, or implement
 * {@see AuditActorResolverInterface} against whatever identity the
 * application already has.
 *
 * @api
 */
final readonly class IdentityAuditActorResolver implements AuditActorResolverInterface
{
    public function __construct(
        private IdentitySourceInterface $identitySource,
        private string $userActorType = 'mcp-user',
        private string $guestActorType = 'mcp-client',
    ) {}

    #[\Override]
    public function resolve(ToolCallContext $context, ?string $sessionId, ?string $clientName): AuditActor
    {
        $userId = $this->identitySource->getId();

        return $userId === null
            ? new AuditActor(type: $this->guestActorType, id: $sessionId, name: $clientName)
            : new AuditActor(type: $this->userActorType, id: $userId, name: $clientName);
    }
}
