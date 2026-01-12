<?php

declare(strict_types=1);

namespace HashNote\Infrastructure\Persistence;

use HashNote\Domain\Message;
use PDO;

class MessageRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function create(Message $message): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO messages (id, message, msg_hash, tx_hash, status, block_number, created_at)
            VALUES (:id, :message, :msg_hash, :tx_hash, :status, :block_number, :created_at)
        ");

        $stmt->execute([
            ':id' => $message->id,
            ':message' => $message->message,
            ':msg_hash' => $message->msgHash,
            ':tx_hash' => $message->txHash,
            ':status' => $message->status,
            ':block_number' => $message->blockNumber,
            ':created_at' => $message->createdAt,
        ]);
    }

    public function findById(string $id): ?Message
    {
        $stmt = $this->pdo->prepare("
            SELECT id, message, msg_hash, tx_hash, status, block_number, confirmed_at, created_at
            FROM messages
            WHERE id = :id
        ");

        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return Message::fromArray($data);
    }

    public function update(Message $message): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE messages
            SET tx_hash = :tx_hash,
                status = :status,
                block_number = :block_number,
                confirmed_at = :confirmed_at
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $message->id,
            ':tx_hash' => $message->txHash,
            ':status' => $message->status,
            ':block_number' => $message->blockNumber,
            ':confirmed_at' => $message->confirmedAt,
        ]);
    }

    public function findPendingWithTxHash(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, message, msg_hash, tx_hash, status, block_number, confirmed_at, created_at
            FROM messages
            WHERE status = 'pending' AND tx_hash IS NOT NULL
        ");

        $stmt->execute();
        $results = $stmt->fetchAll();

        return array_map(fn($data) => Message::fromArray($data), $results);
    }
}

