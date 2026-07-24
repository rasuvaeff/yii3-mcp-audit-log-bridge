<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge;

use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3AuditLog\AuditChange;
use Rasuvaeff\Yii3AuditLog\AuditChangeSet;
use Rasuvaeff\Yii3AuditLog\AuditLogger;
use Rasuvaeff\Yii3AuditLog\AuditMetadata;
use Rasuvaeff\Yii3AuditLog\AuditSubject;
use Rasuvaeff\Yii3Mcp\Interceptor\CallOutcome;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallInterceptorInterface;
use Throwable;

/**
 * Records every MCP tools/call into the audit trail: which agent (client
 * info from the initialize handshake), which tool, the arguments, the
 * outcome and the duration.
 *
 * Each tool argument becomes its own change field, so the AuditLogger's
 * SensitiveValueMasker masks arguments named `password`, `token` etc. the
 * same way it masks any other audited value. Call metadata fields are
 * prefixed with `mcp.` to stay clear of argument names.
 *
 * `mcp.outcome` follows the core's shared {@see CallOutcome} vocabulary:
 * `success`, `rejected` (a client-visible refusal — rate limit, RBAC,
 * budget, or the tool itself throwing ToolCallException) or `error` (an
 * unexpected failure). The interceptor never swallows failures: the
 * exception is recorded and rethrown, so the MCP error envelope the agent
 * sees is unchanged.
 *
 * @api
 */
final readonly class AuditTrailInterceptor implements ToolCallInterceptorInterface
{
    private const string ACTION = 'mcp.tools.call';

    public function __construct(
        private AuditLogger $auditLogger,
        private string $actorType = 'mcp-client',
        private string $subjectType = 'mcp-tool',
    ) {}

    #[\Override]
    public function intercept(ToolCallContext $context, callable $next): mixed
    {
        $startedAt = hrtime(true);

        try {
            /** @var mixed $result */
            $result = $next();
        } catch (Throwable $exception) {
            $this->record($context, $startedAt, outcome: CallOutcome::fromThrowable($exception), error: $exception->getMessage());

            throw $exception;
        }

        $this->record($context, $startedAt, outcome: CallOutcome::Success, error: null);

        return $result;
    }

    private function record(ToolCallContext $context, int $startedAt, CallOutcome $outcome, ?string $error): void
    {
        $changes = [];

        /** @var mixed $value */
        foreach ($context->arguments as $name => $value) {
            $changes[] = new AuditChange(field: $name, oldValue: null, newValue: $value);
        }

        $changes[] = new AuditChange(field: 'mcp.outcome', oldValue: null, newValue: $outcome->value);
        $changes[] = new AuditChange(field: 'mcp.duration_ms', oldValue: null, newValue: intdiv(hrtime(true) - $startedAt, 1_000_000));

        if ($error !== null) {
            $changes[] = new AuditChange(field: 'mcp.error', oldValue: null, newValue: $error);
        }

        $sessionId = $context->session?->getId()->toRfc4122();
        $clientName = $this->clientName($context);

        $this->auditLogger->log(
            actor: new AuditActor(type: $this->actorType, id: $sessionId, name: $clientName),
            action: self::ACTION,
            subject: new AuditSubject(type: $this->subjectType, id: $context->toolName),
            changes: new AuditChangeSet($changes),
            metadata: new AuditMetadata(requestId: $sessionId, userAgent: $clientName),
        );
    }

    private function clientName(ToolCallContext $context): ?string
    {
        $info = $context->getClientInfo();
        $name = $info['name'] ?? null;

        if (!is_string($name) || $name === '') {
            return null;
        }

        /** @var mixed $version */
        $version = $info['version'] ?? null;

        return is_string($version) && $version !== '' ? $name . ' ' . $version : $name;
    }
}
