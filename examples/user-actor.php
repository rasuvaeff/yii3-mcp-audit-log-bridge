<?php

declare(strict_types=1);

use Mcp\Capability\Attribute\McpTool;
use Mcp\Server\Session\InMemorySessionStore;
use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3AuditLog\AuditLogger;
use Rasuvaeff\Yii3AuditLog\InMemoryAuditWriter;
use Rasuvaeff\Yii3AuditLog\SensitiveValueMasker;
use Rasuvaeff\Yii3Mcp\McpServerFactory;
use Rasuvaeff\Yii3Mcp\Testing\McpTester;
use Rasuvaeff\Yii3McpAuditLogBridge\AuditTrailInterceptor;
use Rasuvaeff\Yii3McpAuditLogBridge\IdentityAuditActorResolver;
use Rasuvaeff\Yii3McpRbacBridge\IdentitySourceInterface;
use Yiisoft\Test\Support\Container\SimpleContainer;

require dirname(__DIR__) . '/vendor/autoload.php';

final readonly class ReportTool
{
    /**
     * Exports a report.
     */
    #[McpTool(name: 'report.export')]
    public function export(string $reportId): string
    {
        return "exported {$reportId}";
    }
}

final readonly class ExampleClock implements Psr\Clock\ClockInterface
{
    #[\Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}

/**
 * In an application this is rbac-bridge's CurrentUserIdentitySource — the id
 * the authentication middleware resolved for the current request.
 */
final readonly class StaticIdentitySource implements IdentitySourceInterface
{
    public function __construct(private ?string $id) {}

    #[\Override]
    public function getId(): ?string
    {
        return $this->id;
    }
}

$factory = new Psr17Factory();

// In an application: one params line for the interceptor plus
// AuditActorResolverInterface::class => IdentityAuditActorResolver::class in di
foreach ([['authenticated user', '7'], ['guest', null]] as [$label, $userId]) {
    $writer = new InMemoryAuditWriter();
    $auditLogger = new AuditLogger(writer: $writer, clock: new ExampleClock(), masker: new SensitiveValueMasker());

    $interceptor = new AuditTrailInterceptor(
        $auditLogger,
        new IdentityAuditActorResolver(new StaticIdentitySource($userId)),
    );

    $server = (new McpServerFactory(
        container: new SimpleContainer([ReportTool::class => new ReportTool()]),
        sessionStore: new InMemorySessionStore(),
        name: 'audit-example',
        version: '1.0.0',
    ))->create([ReportTool::class], [], [$interceptor]);

    (new McpTester($server, $factory, $factory, $factory))->callTool('report.export', ['reportId' => 'q3']);

    [$event] = $writer->getEvents();
    $changes = [];

    foreach ($event->getChangeSet()->getChanges() as $change) {
        $changes[$change->getField()] = $change->getNewValue();
    }

    echo $label . ":\n";
    echo '  actor:       ' . $event->getActor()->getType() . ' #' . ($event->getActor()->getId() ?? 'null') . "\n";
    echo '  mcp.session: ' . ($changes['mcp.session'] ?? 'null') . "\n";
    echo '  mcp.client:  ' . ($changes['mcp.client'] ?? 'null') . "\n";
}
