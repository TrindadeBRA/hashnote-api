<?php

declare(strict_types=1);

namespace HashNote\Domain;

class Message
{
    public function __construct(
        public readonly string $id,
        public readonly string $message,
        public readonly string $msgHash,
        public ?string $txHash = null,
        public string $status = 'pending',
        public ?int $blockNumber = null,
        public ?string $confirmedAt = null,
        public readonly string $createdAt = ''
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'msg_hash' => $this->msgHash,
            'tx_hash' => $this->txHash,
            'status' => $this->status,
            'block_number' => $this->blockNumber,
            'confirmed_at' => $this->confirmedAt,
            'created_at' => $this->createdAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            message: $data['message'],
            msgHash: $data['msg_hash'],
            txHash: $data['tx_hash'] ?? null,
            status: $data['status'],
            blockNumber: $data['block_number'] ?? null,
            confirmedAt: $data['confirmed_at'] ?? null,
            createdAt: $data['created_at']
        );
    }
}

