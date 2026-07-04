<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge\Benchmarks\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final readonly class SystemClock implements ClockInterface
{
    #[\Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
