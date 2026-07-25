<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpAuditLogBridge\Tests\Support;

use Rasuvaeff\Yii3McpRbacBridge\IdentitySourceInterface;

final readonly class FakeIdentitySource implements IdentitySourceInterface
{
    public function __construct(
        private ?string $id,
    ) {}

    #[\Override]
    public function getId(): ?string
    {
        return $this->id;
    }
}
