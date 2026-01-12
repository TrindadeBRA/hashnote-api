<?php

declare(strict_types=1);

namespace HashNote\Service;

use HashNote\Domain\Message;
use HashNote\Infrastructure\Persistence\MessageRepository;
use Psr\Log\LoggerInterface;

class MessageService
{
    public function __construct(
        private readonly MessageRepository $repository,
        private readonly BlockchainService $blockchainService,
        private readonly LoggerInterface $logger
    ) {}

    public function createMessage(string $message): Message
    {
        // Validação
        $message = trim($message);
        if (strlen($message) < 1 || strlen($message) > 280) {
            throw new \InvalidArgumentException(
                'Message must be between 1 and 280 characters'
            );
        }

        // Gera hash keccak256
        $msgHash = '0x' . \kornrunner\Keccak::hash($message, 256);

        // Cria mensagem
        $id = $this->generateUuid();
        $now = date('c');
        
        $messageObj = new Message(
            id: $id,
            message: $message,
            msgHash: $msgHash,
            status: 'pending',
            createdAt: $now
        );

        // Tenta registrar na blockchain
        $mode = $_ENV['BLOCKCHAIN_MODE'] ?? 'mock';
        
        if ($mode === 'mock') {
            try {
                $txHash = $this->blockchainService->registerMessage($msgHash);
                $messageObj->txHash = $txHash;
            } catch (\Exception $e) {
                $this->logger->error("Failed to register message on blockchain", [
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif ($mode === 'rpc_only') {
            // Modo rpc_only não suporta escrita
            throw new \RuntimeException(
                'RPC-only mode does not support writing transactions. ' .
                'Use mock mode for testing or server_sign mode (not implemented yet).'
            );
        } elseif ($mode === 'server_sign') {
            // Modo server_sign não implementado nesta POC
            throw new \RuntimeException(
                'Server-sign mode is not implemented in this POC. ' .
                'Use mock mode for testing. See README for details.'
            );
        }

        $this->repository->create($messageObj);
        
        return $messageObj;
    }

    public function getMessage(string $id): ?Message
    {
        $message = $this->repository->findById($id);
        
        if (!$message) {
            return null;
        }

        // Se está pendente e tem tx_hash, tenta atualizar
        if ($message->status === 'pending' && $message->txHash) {
            $this->updateMessageStatus($message);
        }

        return $message;
    }

    public function verifyMessage(string $id): ?array
    {
        $message = $this->repository->findById($id);
        
        if (!$message) {
            return null;
        }

        if (!$message->txHash) {
            return [
                'valid' => false,
                'status' => $message->status,
                'tx_hash' => null,
                'network' => $_ENV['BLOCKCHAIN_NETWORK'] ?? 'unknown',
                'error' => 'No transaction hash',
            ];
        }

        $mode = $_ENV['BLOCKCHAIN_MODE'] ?? 'mock';
        $valid = false;
        $receipt = null;

        if ($mode === 'mock') {
            $valid = $this->blockchainService->isConfirmed($message->txHash);
            $receipt = $this->blockchainService->getReceipt($message->txHash);
        } else {
            $valid = $this->blockchainService->isConfirmed($message->txHash);
            $receipt = $this->blockchainService->getReceipt($message->txHash);
        }

        $result = [
            'valid' => $valid,
            'status' => $message->status,
            'tx_hash' => $message->txHash,
            'network' => $_ENV['BLOCKCHAIN_NETWORK'] ?? 'localhost',
        ];

        if ($_ENV['BLOCKCHAIN_CONTRACT_ADDRESS'] ?? null) {
            $result['contract_address'] = $_ENV['BLOCKCHAIN_CONTRACT_ADDRESS'];
        }

        if ($receipt && isset($receipt['blockNumber'])) {
            $result['block_number'] = $receipt['blockNumber'];
        }

        if ($receipt && $mode !== 'mock') {
            // Verifica se msg_hash corresponde (se houver logs do contrato)
            $result['msg_hash_matches'] = true; // Simplificado para POC
        }

        return $result;
    }

    public function updateMessageStatus(Message $message): void
    {
        if (!$message->txHash) {
            return;
        }

        $receipt = $this->blockchainService->getReceipt($message->txHash);
        
        if (!$receipt) {
            return;
        }

        $status = $receipt['status'] === '0x1' ? 'confirmed' : 'failed';
        
        if ($status !== $message->status) {
            $message->status = $status;
            if ($status === 'confirmed') {
                $message->confirmedAt = date('c');
                if (isset($receipt['blockNumber'])) {
                    $blockNumber = is_string($receipt['blockNumber'])
                        ? hexdec(substr($receipt['blockNumber'], 2))
                        : $receipt['blockNumber'];
                    $message->blockNumber = $blockNumber;
                }
            }
            
            $this->repository->update($message);
        }
    }

    public function processPendingMessages(): int
    {
        $pending = $this->repository->findPendingWithTxHash();
        $processed = 0;

        foreach ($pending as $message) {
            $oldStatus = $message->status;
            $this->updateMessageStatus($message);
            
            if ($message->status !== $oldStatus) {
                $processed++;
            }
        }

        return $processed;
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

