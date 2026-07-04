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
use Yiisoft\Test\Support\Container\SimpleContainer;

require dirname(__DIR__) . '/vendor/autoload.php';

final readonly class BillingTool
{
    /**
     * Charges an order.
     */
    #[McpTool(name: 'billing.charge')]
    public function charge(string $orderId, string $password): string
    {
        return "charged {$orderId}";
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

// In an application everything below is DI + one params line:
// 'rasuvaeff/yii3-mcp' => ['interceptors' => [AuditTrailInterceptor::class]]
$writer = new InMemoryAuditWriter();
$auditLogger = new AuditLogger(
    writer: $writer,
    clock: new ExampleClock(),
    masker: new SensitiveValueMasker(),
);

$factory = new Psr17Factory();
$server = (new McpServerFactory(
    container: new SimpleContainer([BillingTool::class => new BillingTool()]),
    sessionStore: new InMemorySessionStore(),
    name: 'audit-example',
    version: '1.0.0',
))->create([BillingTool::class], [], [new AuditTrailInterceptor($auditLogger)]);

$tester = new McpTester($server, $factory, $factory, $factory);
$result = $tester->callTool('billing.charge', ['orderId' => '42', 'password' => 'p@ss']);
echo 'tool result: ' . $result['content'][0]['text'] . "\n";

[$event] = $writer->getEvents();
echo "audit event:\n";
echo '  actor:   ' . $event->getActor()->getType() . ' "' . $event->getActor()->getName() . "\"\n";
echo '  action:  ' . $event->getAction() . "\n";
echo '  subject: ' . $event->getSubject()->getType() . ' ' . $event->getSubject()->getId() . "\n";

foreach ($event->getChangeSet()->getChanges() as $change) {
    echo '  change:  ' . $change->getField() . ' = ' . json_encode($change->getNewValue()) . "\n";
}
