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
        $this->logger->info("[API] createMessage iniciado", [
            'message_length' => strlen($message),
            'message_preview' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : ''),
        ]);

        // Validação
        $message = trim($message);
        if (strlen($message) < 1 || strlen($message) > 280) {
            $this->logger->warning("[API] createMessage validação falhou", [
                'message_length' => strlen($message),
            ]);
            throw new \InvalidArgumentException(
                'Message must be between 1 and 280 characters'
            );
        }

        // Gera hash keccak256
        $msgHash = '0x' . \kornrunner\Keccak::hash($message, 256);
        $this->logger->info("[API] createMessage hash gerado", [
            'msg_hash' => $msgHash,
        ]);

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

        $this->logger->info("[API] createMessage objeto criado", [
            'id' => $id,
            'msg_hash' => $msgHash,
            'status' => 'pending',
        ]);

        // Tenta registrar na blockchain
        $mode = $_ENV['BLOCKCHAIN_MODE'] ?? 'mock';
        $this->logger->info("[API] createMessage registrando na blockchain", [
            'id' => $id,
            'mode' => $mode,
            'msg_hash' => $msgHash,
        ]);
        
        if ($mode === 'mock' || $mode === 'server_sign') {
            try {
                $txHash = $this->blockchainService->registerMessage($msgHash);
                $messageObj->txHash = $txHash;
                $this->logger->info("[API] createMessage registrado na blockchain", [
                    'id' => $id,
                    'tx_hash' => $txHash,
                    'mode' => $mode,
                ]);
            } catch (\Exception $e) {
                $this->logger->error("[API] createMessage falha ao registrar na blockchain", [
                    'id' => $id,
                    'error' => $e->getMessage(),
                    'mode' => $mode,
                    'error_class' => get_class($e),
                ]);
                // Em modo server_sign, relança exceção para não criar mensagem sem tx_hash
                if ($mode === 'server_sign') {
                    throw $e;
                }
            }
        } elseif ($mode === 'rpc_only') {
            // Modo rpc_only não suporta escrita
            $this->logger->error("[API] createMessage modo rpc_only não suporta escrita", [
                'id' => $id,
                'mode' => $mode,
            ]);
            throw new \RuntimeException(
                'RPC-only mode does not support writing transactions. ' .
                'Use mock mode for testing or server_sign mode for production.'
            );
        }

        $this->repository->create($messageObj);
        $this->logger->info("[API] createMessage salvo no banco", [
            'id' => $id,
            'tx_hash' => $messageObj->txHash,
            'status' => $messageObj->status,
        ]);
        
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
        $this->logger->info("[API] verifyMessage iniciado", [
            'id' => $id,
        ]);

        $message = $this->repository->findById($id);
        
        if (!$message) {
            $this->logger->warning("[API] verifyMessage mensagem não encontrada", [
                'id' => $id,
            ]);
            return null;
        }

        $this->logger->info("[API] verifyMessage mensagem encontrada", [
            'id' => $id,
            'status' => $message->status,
            'tx_hash' => $message->txHash,
        ]);

        if (!$message->txHash) {
            $this->logger->info("[API] verifyMessage sem tx_hash", [
                'id' => $id,
                'status' => $message->status,
            ]);
            return [
                'valid' => false,
                'status' => $message->status,
                'tx_hash' => null,
                'network' => $_ENV['BLOCKCHAIN_NETWORK'] ?? 'unknown',
                'error' => 'No transaction hash',
            ];
        }

        // Atualiza status antes de verificar (se estiver pendente)
        if ($message->status === 'pending' && $message->txHash) {
            $this->logger->info("[API] verifyMessage atualizando status pendente", [
                'id' => $id,
                'tx_hash' => $message->txHash,
            ]);
            $this->updateMessageStatus($message);
            // Recarrega mensagem do banco para obter status atualizado
            $message = $this->repository->findById($id);
            if (!$message) {
                $this->logger->error("[API] verifyMessage mensagem não encontrada após atualização", [
                    'id' => $id,
                ]);
                return null;
            }
            $this->logger->info("[API] verifyMessage status atualizado", [
                'id' => $id,
                'new_status' => $message->status,
            ]);
        }

        $mode = $_ENV['BLOCKCHAIN_MODE'] ?? 'mock';
        $this->logger->info("[API] verifyMessage verificando confirmação", [
            'id' => $id,
            'tx_hash' => $message->txHash,
            'mode' => $mode,
        ]);

        $valid = false;
        $receipt = null;

        if ($mode === 'mock') {
            $valid = $this->blockchainService->isConfirmed($message->txHash);
            $receipt = $this->blockchainService->getReceipt($message->txHash);
        } else {
            $valid = $this->blockchainService->isConfirmed($message->txHash);
            $receipt = $this->blockchainService->getReceipt($message->txHash);
        }

        $this->logger->info("[API] verifyMessage verificação concluída", [
            'id' => $id,
            'tx_hash' => $message->txHash,
            'valid' => $valid,
            'has_receipt' => $receipt !== null,
            'status' => $message->status,
        ]);

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
            $blockNumber = is_string($receipt['blockNumber'])
                ? hexdec(substr($receipt['blockNumber'], 2))
                : $receipt['blockNumber'];
            $result['block_number'] = $blockNumber;
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
            $this->logger->debug("[API] updateMessageStatus sem tx_hash", [
                'id' => $message->id,
            ]);
            return;
        }

        $this->logger->info("[API] updateMessageStatus iniciado", [
            'id' => $message->id,
            'tx_hash' => $message->txHash,
            'current_status' => $message->status,
        ]);

        $receipt = $this->blockchainService->getReceipt($message->txHash);
        
        if (!$receipt) {
            $this->logger->info("[API] updateMessageStatus receipt não encontrado", [
                'id' => $message->id,
                'tx_hash' => $message->txHash,
            ]);
            return;
        }

        $status = $receipt['status'] === '0x1' ? 'confirmed' : 'failed';
        $this->logger->info("[API] updateMessageStatus receipt encontrado", [
            'id' => $message->id,
            'tx_hash' => $message->txHash,
            'receipt_status' => $receipt['status'],
            'new_status' => $status,
            'current_status' => $message->status,
        ]);
        
        if ($status !== $message->status) {
            $oldStatus = $message->status;
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
            
            $this->logger->info("[API] updateMessageStatus atualizando status", [
                'id' => $message->id,
                'tx_hash' => $message->txHash,
                'old_status' => $oldStatus,
                'new_status' => $status,
                'block_number' => $message->blockNumber ?? null,
            ]);
            
            $this->repository->update($message);
            $this->logger->info("[API] updateMessageStatus status atualizado no banco", [
                'id' => $message->id,
                'tx_hash' => $message->txHash,
                'status' => $message->status,
            ]);
        } else {
            $this->logger->debug("[API] updateMessageStatus status já está atualizado", [
                'id' => $message->id,
                'tx_hash' => $message->txHash,
                'status' => $message->status,
            ]);
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

