<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge;

use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;

/**
 * Decides WHO the audit trail credits for a tools/call. The shipped
 * {@see ClientAuditActorResolver} credits the MCP connection (session id +
 * handshake client name); an application that authenticates its MCP
 * endpoint implements this to credit the user instead, so the journal can
 * answer "which user did what" long after the session store dropped the
 * session.
 *
 * The session id and the formatted client name are passed in because the
 * interceptor already derived them — a resolver that falls back to the
 * connection (a guest call, say) does not have to re-read the handshake.
 * Both are null on transports without a session.
 *
 * Resolvers must not swallow failures: an exception propagates and the
 * tools/call fails loudly rather than being recorded under the wrong actor.
 *
 * @api
 */
interface AuditActorResolverInterface
{
    public function resolve(ToolCallContext $context, ?string $sessionId, ?string $clientName): AuditActor;
}
