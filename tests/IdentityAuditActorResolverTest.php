<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge\Tests;

use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;
use Rasuvaeff\Yii3McpAuditLogBridge\IdentityAuditActorResolver;
use Rasuvaeff\Yii3McpAuditLogBridge\Tests\Support\FakeIdentitySource;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(IdentityAuditActorResolver::class)]
final class IdentityAuditActorResolverTest
{
    public function creditsTheAuthenticatedUser(): void
    {
        $actor = (new IdentityAuditActorResolver(new FakeIdentitySource('42')))->resolve(
            new ToolCallContext(toolName: 'x', arguments: []),
            sessionId: 'session-1',
            clientName: 'claude-code 2.0',
        );

        // the user id, not the session id: the audit row must still name an
        // owner years later, long after the session store dropped the session
        Assert::same($actor->getType(), 'mcp-user');
        Assert::same($actor->getId(), '42');
        Assert::same($actor->getName(), 'claude-code 2.0');
    }

    public function guestFallsBackToTheConnection(): void
    {
        $actor = (new IdentityAuditActorResolver(new FakeIdentitySource(null)))->resolve(
            new ToolCallContext(toolName: 'x', arguments: []),
            sessionId: 'session-1',
            clientName: 'claude-code 2.0',
        );

        Assert::same($actor->getType(), 'mcp-client');
        Assert::same($actor->getId(), 'session-1');
    }

    public function actorTypesAreConfigurable(): void
    {
        $resolver = new IdentityAuditActorResolver(
            new FakeIdentitySource('42'),
            userActorType: 'human',
            guestActorType: 'anonymous',
        );
        $context = new ToolCallContext(toolName: 'x', arguments: []);

        Assert::same($resolver->resolve($context, null, null)->getType(), 'human');

        $guests = new IdentityAuditActorResolver(
            new FakeIdentitySource(null),
            userActorType: 'human',
            guestActorType: 'anonymous',
        );

        Assert::same($guests->resolve($context, null, null)->getType(), 'anonymous');
    }
}
