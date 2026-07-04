<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge\Tests\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final readonly class FixedClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now = new DateTimeImmutable('2026-07-04T12:00:00+00:00'),
    ) {}

    #[\Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
