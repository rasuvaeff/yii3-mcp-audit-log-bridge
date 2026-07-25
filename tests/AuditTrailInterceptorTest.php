<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge\Tests;

use Mcp\Exception\ToolCallException;
use Mcp\Server;
use Mcp\Server\Session\InMemorySessionStore;
use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3AuditLog\AuditEvent;
use Rasuvaeff\Yii3AuditLog\AuditLogger;
use Rasuvaeff\Yii3AuditLog\InMemoryAuditWriter;
use Rasuvaeff\Yii3AuditLog\SensitiveValueMasker;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;
use Rasuvaeff\Yii3Mcp\McpServerFactory;
use Rasuvaeff\Yii3Mcp\Testing\McpTester;
use Rasuvaeff\Yii3McpAuditLogBridge\AuditTrailInterceptor;
use Rasuvaeff\Yii3McpAuditLogBridge\ClientAuditActorResolver;
use Rasuvaeff\Yii3McpAuditLogBridge\IdentityAuditActorResolver;
use Rasuvaeff\Yii3McpAuditLogBridge\Tests\Support\FakeIdentitySource;
use Rasuvaeff\Yii3McpAuditLogBridge\Tests\Support\FakeSession;
use Rasuvaeff\Yii3McpAuditLogBridge\Tests\Support\FixedClock;
use Rasuvaeff\Yii3McpAuditLogBridge\Tests\Support\OrderTool;
use Rasuvaeff\Yii3McpAuditLogBridge\Tests\Support\ThrowingActorResolver;
use RuntimeException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Test\Support\Container\SimpleContainer;

#[Test]
#[Covers(AuditTrailInterceptor::class)]
final class AuditTrailInterceptorTest
{
    private InMemoryAuditWriter $writer;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->writer = new InMemoryAuditWriter();
    }

    public function recordsSuccessfulCallWithActorSubjectAndArguments(): void
    {
        $this->tester()->callTool('order.status', ['orderId' => '42', 'password' => 'p@ss']);

        $event = $this->singleEvent();
        Assert::same($event->getAction(), 'mcp.tools.call');
        Assert::same($event->getActor()->getType(), 'mcp-client');
        Assert::same($event->getActor()->getName(), 'mcp-tester 1.0');
        Assert::same($event->getSubject()->getType(), 'mcp-tool');
        Assert::same($event->getSubject()->getId(), 'order.status');

        $changes = $this->changesByField($event);
        Assert::same($changes['orderId'], '42');
        Assert::same($changes['mcp.outcome'], 'success');
        Assert::true($changes['mcp.duration_ms'] >= 0);
        Assert::false(array_key_exists('mcp.error', $changes));
    }

    public function masksSensitiveArgumentsByFieldName(): void
    {
        $this->tester()->callTool('order.status', ['orderId' => '42', 'password' => 'p@ss']);

        $changes = $this->changesByField($this->singleEvent());
        Assert::same($changes['password'], '***');
        Assert::same($changes['orderId'], '42');
    }

    public function recordsFailureBeforeTheErrorPropagates(): void
    {
        $caught = null;

        try {
            $this->tester()->callTool('order.fail');
        } catch (RuntimeException $caught) {
        }

        // the SDK turns the tool exception into a JSON-RPC error as usual —
        // the bridge audited the ORIGINAL message before rethrowing
        Assert::notNull($caught);

        $changes = $this->changesByField($this->singleEvent());
        Assert::same($changes['mcp.outcome'], 'error');
        Assert::same($changes['mcp.error'], 'upstream unavailable');
    }

    public function clientVisibleRejectionIsRecordedAsRejectedNotError(): void
    {
        $interceptor = new AuditTrailInterceptor($this->auditLogger());
        $context = new ToolCallContext(toolName: 'x', arguments: []);

        $caught = null;

        try {
            $interceptor->intercept($context, static fn(): mixed => throw new ToolCallException('rate limit exceeded'));
        } catch (ToolCallException $caught) {
        }

        Assert::notNull($caught);

        $changes = $this->changesByField($this->singleEvent());
        Assert::same($changes['mcp.outcome'], 'rejected');
        Assert::same($changes['mcp.error'], 'rate limit exceeded');
    }

    public function sessionIdBecomesActorIdAndRequestId(): void
    {
        $this->tester()->callTool('order.status', ['orderId' => '1', 'password' => 'x']);

        $event = $this->singleEvent();
        $actorId = $event->getActor()->getId();
        Assert::notNull($actorId);
        Assert::same($event->getMetadata()?->getRequestId(), $actorId);
        Assert::same($event->getMetadata()?->getUserAgent(), 'mcp-tester 1.0');
    }

    public function rethrowsWithoutSwallowingTheException(): void
    {
        $interceptor = new AuditTrailInterceptor($this->auditLogger());
        $context = new ToolCallContext(toolName: 'x', arguments: []);

        $caught = null;

        try {
            $interceptor->intercept($context, static fn(): mixed => throw new RuntimeException('boom'));
        } catch (RuntimeException $caught) {
        }

        Assert::notNull($caught);
        Assert::same($caught->getMessage(), 'boom');
        Assert::same(count($this->writer->getEvents()), 1);
    }

    public function withoutSessionActorIdAndClientNameAreNull(): void
    {
        $interceptor = new AuditTrailInterceptor($this->auditLogger());
        $context = new ToolCallContext(toolName: 'x', arguments: []);

        Assert::same($interceptor->intercept($context, static fn(): string => 'ok'), 'ok');

        $event = $this->singleEvent();
        Assert::null($event->getActor()->getId());
        Assert::null($event->getActor()->getName());
    }

    public function clientNameWithoutVersionIsBareName(): void
    {
        $interceptor = new AuditTrailInterceptor($this->auditLogger());
        $session = new FakeSession(['client_info' => ['name' => 'claude']]);
        $context = new ToolCallContext(toolName: 'x', arguments: [], session: $session);

        $interceptor->intercept($context, static fn(): string => 'ok');

        Assert::same($this->singleEvent()->getActor()->getName(), 'claude');
    }

    public function emptyClientNameBecomesNull(): void
    {
        $interceptor = new AuditTrailInterceptor($this->auditLogger());
        $session = new FakeSession(['client_info' => ['name' => '', 'version' => '1.0']]);
        $context = new ToolCallContext(toolName: 'x', arguments: [], session: $session);

        $interceptor->intercept($context, static fn(): string => 'ok');

        Assert::null($this->singleEvent()->getActor()->getName());
    }

    public function durationReflectsWallTimeOfTheWrappedChain(): void
    {
        $interceptor = new AuditTrailInterceptor($this->auditLogger());
        $context = new ToolCallContext(toolName: 'x', arguments: []);

        $interceptor->intercept($context, static function (): string {
            usleep(15_000);

            return 'ok';
        });

        $duration = $this->changesByField($this->singleEvent())['mcp.duration_ms'];
        Assert::true($duration >= 10);
        Assert::true($duration < 10_000);
    }

    public function actorComesFromTheResolverAndSubjectTypeIsConfigurable(): void
    {
        $interceptor = new AuditTrailInterceptor(
            $this->auditLogger(),
            new ClientAuditActorResolver(actorType: 'agent'),
            subjectType: 'operation',
        );
        $context = new ToolCallContext(toolName: 'x', arguments: []);

        $interceptor->intercept($context, static fn(): string => 'ok');

        $event = $this->singleEvent();
        Assert::same($event->getActor()->getType(), 'agent');
        Assert::same($event->getSubject()->getType(), 'operation');
    }

    public function resolverDecidesTheActorWhileTheConnectionStaysInTheChangeSet(): void
    {
        $interceptor = new AuditTrailInterceptor(
            $this->auditLogger(),
            new IdentityAuditActorResolver(new FakeIdentitySource('42')),
        );
        $session = new FakeSession(['client_info' => ['name' => 'claude-code', 'version' => '2.0']]);
        $context = new ToolCallContext(toolName: 'x', arguments: [], session: $session, clientId: 'ci-runner');

        $interceptor->intercept($context, static fn(): string => 'ok');

        $event = $this->singleEvent();
        Assert::same($event->getActor()->getType(), 'mcp-user');
        Assert::same($event->getActor()->getId(), '42');

        // the connection left actor_id — it must not leave the record
        $changes = $this->changesByField($event);
        Assert::same($changes['mcp.session'], $event->getMetadata()?->getRequestId());
        Assert::same($changes['mcp.client'], 'claude-code 2.0');
        Assert::same($changes['mcp.client_id'], 'ci-runner');
    }

    public function clientIdIsOmittedWhenTheTransportCarriesNone(): void
    {
        $interceptor = new AuditTrailInterceptor($this->auditLogger());
        $context = new ToolCallContext(toolName: 'x', arguments: []);

        $interceptor->intercept($context, static fn(): string => 'ok');

        $changes = $this->changesByField($this->singleEvent());
        Assert::false(array_key_exists('mcp.client_id', $changes));
        Assert::null($changes['mcp.session']);
        Assert::null($changes['mcp.client']);
    }

    public function defaultResolverKeepsTheConnectionAsActorWithoutAnyBinding(): void
    {
        // the constructor default is what an application gets when its
        // container has no AuditActorResolverInterface binding at all —
        // yiisoft/di falls back to the parameter default, so wiring the
        // interceptor by FQCN alone must keep working
        $parameter = (new \ReflectionClass(AuditTrailInterceptor::class))->getConstructor()?->getParameters()[1] ?? null;

        Assert::notNull($parameter);
        Assert::true($parameter->isDefaultValueAvailable());
        Assert::instanceOf($parameter->getDefaultValue(), ClientAuditActorResolver::class);

        (new AuditTrailInterceptor($this->auditLogger()))->intercept(
            new ToolCallContext(toolName: 'x', arguments: []),
            static fn(): string => 'ok',
        );

        Assert::same($this->singleEvent()->getActor()->getType(), 'mcp-client');
    }

    public function resolverFailureIsNotSwallowed(): void
    {
        $interceptor = new AuditTrailInterceptor($this->auditLogger(), new ThrowingActorResolver());
        $caught = null;

        try {
            $interceptor->intercept(
                new ToolCallContext(toolName: 'x', arguments: []),
                static fn(): string => 'ok',
            );
        } catch (RuntimeException $caught) {
        }

        // the tool already ran — a broken resolver must fail loudly instead
        // of writing the call under the wrong actor or dropping it silently
        Assert::notNull($caught);
        Assert::same($caught->getMessage(), 'identity backend down');
        Assert::same(count($this->writer->getEvents()), 0);
    }

    private function auditLogger(): AuditLogger
    {
        return new AuditLogger(
            writer: $this->writer,
            clock: new FixedClock(),
            masker: new SensitiveValueMasker(),
        );
    }

    private function singleEvent(): AuditEvent
    {
        $events = $this->writer->getEvents();
        Assert::same(count($events), 1);

        return $events[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function changesByField(AuditEvent $event): array
    {
        $byField = [];

        foreach ($event->getChangeSet()->getChanges() as $change) {
            $byField[$change->getField()] = $change->getNewValue();
        }

        return $byField;
    }

    private function tester(): McpTester
    {
        $factory = new Psr17Factory();

        return new McpTester(
            server: $this->server(),
            requestFactory: $factory,
            responseFactory: $factory,
            streamFactory: $factory,
        );
    }

    private function server(): Server
    {
        return (new McpServerFactory(
            container: new SimpleContainer([OrderTool::class => new OrderTool()]),
            sessionStore: new InMemorySessionStore(),
            name: 'audit-suite',
            version: '1.0.0',
        ))->create([OrderTool::class], [], [new AuditTrailInterceptor($this->auditLogger())]);
    }
}
