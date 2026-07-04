<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge\Tests\Support;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class OrderTool
{
    /**
     * Returns the status of an order.
     */
    #[McpTool(name: 'order.status')]
    public function status(string $orderId, string $password): string
    {
        return 'paid:' . $orderId;
    }

    #[McpTool(name: 'order.fail')]
    public function fail(): string
    {
        throw new RuntimeException('upstream unavailable');
    }
}
