<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge\Tests;

use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;
use Rasuvaeff\Yii3McpAuditLogBridge\ClientAuditActorResolver;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(ClientAuditActorResolver::class)]
final class ClientAuditActorResolverTest
{
    public function creditsTheConnection(): void
    {
        $actor = (new ClientAuditActorResolver())->resolve(
            new ToolCallContext(toolName: 'x', arguments: []),
            sessionId: 'session-1',
            clientName: 'claude-code 2.0',
        );

        Assert::same($actor->getType(), 'mcp-client');
        Assert::same($actor->getId(), 'session-1');
        Assert::same($actor->getName(), 'claude-code 2.0');
    }

    /**
     * No session to name the connection (stateless era, stdio): the client
     * id is the next best identity. A session, when there is one, wins.
     */
    public function fallsBackToTheClientIdWithoutASession(): void
    {
        $resolver = new ClientAuditActorResolver();
        $context = new ToolCallContext(toolName: 'x', arguments: [], clientId: 'ci-runner');

        Assert::same($resolver->resolve($context, sessionId: null, clientName: null)->getId(), 'ci-runner');
        Assert::same($resolver->resolve($context, sessionId: 'session-1', clientName: null)->getId(), 'session-1');
    }

    public function actorTypeIsConfigurable(): void
    {
        $actor = (new ClientAuditActorResolver(actorType: 'agent'))->resolve(
            new ToolCallContext(toolName: 'x', arguments: []),
            sessionId: null,
            clientName: null,
        );

        Assert::same($actor->getType(), 'agent');
        Assert::null($actor->getId());
        Assert::null($actor->getName());
    }
}
