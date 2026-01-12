<?php

declare(strict_types=1);

namespace HashNote\Infrastructure\Blockchain;

use HashNote\Domain\Blockchain\BlockchainClient;
use Psr\Log\LoggerInterface;

class MockBlockchainClient implements BlockchainClient
{
    private array $transactions = [];
    private array $pendingConfirmations = [];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function registerMessage(string $msgHash): string
    {
        $txHash = '0x' . bin2hex(random_bytes(32));
        $this->transactions[$txHash] = [
            'tx_hash' => $txHash,
            'msg_hash' => $msgHash,
            'status' => 'pending',
            'created_at' => time(),
        ];
        
        // Agenda confirmação após 5-10 segundos
        $confirmAfter = time() + rand(5, 10);
        $this->pendingConfirmations[$txHash] = $confirmAfter;
        
        $this->logger->info("Mock: Registrada mensagem", [
            'msg_hash' => $msgHash,
            'tx_hash' => $txHash,
            'confirm_after' => $confirmAfter,
        ]);

        return $txHash;
    }

    public function getReceipt(string $txHash): ?array
    {
        // Se a transação não existe na memória (container reiniciou, etc)
        // assume que já passou tempo suficiente e confirma
        if (!isset($this->transactions[$txHash])) {
            $this->transactions[$txHash] = [
                'tx_hash' => $txHash,
                'msg_hash' => '', // Não sabemos, mas não importa para mock
                'status' => 'confirmed',
                'created_at' => time() - 60, // Assume que foi criada há 1 minuto
                'block_number' => rand(1000000, 9999999),
                'confirmed_at' => date('c'),
            ];
            
            $this->logger->info("Mock: Transação não encontrada na memória, confirmando automaticamente", [
                'tx_hash' => $txHash,
            ]);
        }

        $tx = $this->transactions[$txHash];
        $now = time();

        // Se está pendente e já passou o tempo, confirma
        if ($tx['status'] === 'pending' && isset($this->pendingConfirmations[$txHash])) {
            if ($now >= $this->pendingConfirmations[$txHash]) {
                $tx['status'] = 'confirmed';
                $tx['block_number'] = rand(1000000, 9999999);
                $tx['confirmed_at'] = date('c');
                $this->transactions[$txHash] = $tx;
                unset($this->pendingConfirmations[$txHash]);
            }
        }

        return [
            'transactionHash' => $txHash,
            'status' => $tx['status'] === 'confirmed' ? '0x1' : '0x0',
            'blockNumber' => $tx['block_number'] ?? null,
            'logs' => [],
        ];
    }

    public function isConfirmed(string $txHash): bool
    {
        $receipt = $this->getReceipt($txHash);
        if (!$receipt) {
            return false;
        }

        // Processa confirmação pendente se necessário
        $this->getReceipt($txHash);

        return isset($this->transactions[$txHash]) 
            && $this->transactions[$txHash]['status'] === 'confirmed';
    }
}

