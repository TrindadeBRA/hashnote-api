<?php

declare(strict_types=1);

namespace HashNote\Service;

use HashNote\Domain\Blockchain\BlockchainClient;
use Psr\Log\LoggerInterface;

class BlockchainService
{
    public function __construct(
        private readonly BlockchainClient $client,
        private readonly LoggerInterface $logger
    ) {}

    public function registerMessage(string $msgHash): string
    {
        return $this->client->registerMessage($msgHash);
    }

    public function getReceipt(string $txHash): ?array
    {
        return $this->client->getReceipt($txHash);
    }

    public function isConfirmed(string $txHash): bool
    {
        return $this->client->isConfirmed($txHash);
    }
}

