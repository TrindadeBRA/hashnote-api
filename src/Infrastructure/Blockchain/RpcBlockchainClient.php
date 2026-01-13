<?php

declare(strict_types=1);

namespace HashNote\Infrastructure\Blockchain;

use HashNote\Domain\Blockchain\BlockchainClient;
use Psr\Log\LoggerInterface;

class RpcBlockchainClient implements BlockchainClient
{
    public function __construct(
        private readonly string $rpcUrl,
        private readonly ?string $contractAddress = null,
        private readonly ?LoggerInterface $logger = null
    ) {}

    public function registerMessage(string $msgHash): string
    {
        // Modo rpc_only não suporta escrita (exige assinatura)
        throw new \RuntimeException(
            'RPC-only mode does not support writing transactions. ' .
            'Use server_sign mode or mock mode for testing.'
        );
    }

    public function getReceipt(string $txHash): ?array
    {
        $this->logger?->info("[BLOCKCHAIN] RPC: eth_getTransactionReceipt", [
            'tx_hash' => $txHash,
        ]);
        
        $response = $this->rpcCall('eth_getTransactionReceipt', [$txHash]);
        
        if (!$response || !isset($response['result']) || $response['result'] === null) {
            $this->logger?->info("[BLOCKCHAIN] RPC: eth_getTransactionReceipt - Receipt não encontrado", [
                'tx_hash' => $txHash,
                'has_response' => $response !== null,
                'has_result' => isset($response['result']),
            ]);
            return null;
        }

        $receipt = $response['result'];
        $status = $receipt['status'] ?? '0x0';
        $blockNumber = $receipt['blockNumber'] ?? null;
        $logsCount = count($receipt['logs'] ?? []);
        
        $this->logger?->info("[BLOCKCHAIN] RPC: eth_getTransactionReceipt - Receipt encontrado", [
            'tx_hash' => $txHash,
            'status' => $status,
            'block_number' => $blockNumber,
            'logs_count' => $logsCount,
        ]);
        
        return [
            'transactionHash' => $receipt['transactionHash'] ?? $txHash,
            'status' => $status,
            'blockNumber' => $blockNumber,
            'logs' => $receipt['logs'] ?? [],
        ];
    }

    public function isConfirmed(string $txHash): bool
    {
        $this->logger?->info("[BLOCKCHAIN] Verificando confirmação", [
            'tx_hash' => $txHash,
            'has_contract' => $this->contractAddress !== null,
        ]);
        
        $receipt = $this->getReceipt($txHash);
        if (!$receipt) {
            $this->logger?->info("[BLOCKCHAIN] Transação não confirmada - Receipt não encontrado", [
                'tx_hash' => $txHash,
            ]);
            return false;
        }

        // Status 0x1 = sucesso, 0x0 = falha
        if ($receipt['status'] !== '0x1') {
            $this->logger?->info("[BLOCKCHAIN] Transação não confirmada - Status inválido", [
                'tx_hash' => $txHash,
                'status' => $receipt['status'],
            ]);
            return false;
        }

        // Se há contrato configurado, verifica se há logs do contrato
        if ($this->contractAddress) {
            $contractAddressLower = strtolower($this->contractAddress);
            foreach ($receipt['logs'] as $log) {
                if (isset($log['address']) && strtolower($log['address']) === $contractAddressLower) {
                    $this->logger?->info("[BLOCKCHAIN] Transação confirmada - Log do contrato encontrado", [
                        'tx_hash' => $txHash,
                        'contract_address' => $this->contractAddress,
                        'block_number' => $receipt['blockNumber'],
                    ]);
                    return true;
                }
            }
            $this->logger?->info("[BLOCKCHAIN] Transação não confirmada - Log do contrato não encontrado", [
                'tx_hash' => $txHash,
                'contract_address' => $this->contractAddress,
                'logs_count' => count($receipt['logs']),
            ]);
            return false;
        }

        $this->logger?->info("[BLOCKCHAIN] Transação confirmada", [
            'tx_hash' => $txHash,
            'block_number' => $receipt['blockNumber'],
        ]);
        return true;
    }

    private function rpcCall(string $method, array $params = []): ?array
    {
        $startTime = microtime(true);
        $data = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1,
        ];

        $this->logger?->debug("[BLOCKCHAIN] RPC Call iniciado", [
            'method' => $method,
            'params' => $params,
            'url' => $this->rpcUrl,
        ]);

        $ch = curl_init($this->rpcUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            $this->logger?->error("[BLOCKCHAIN] RPC Call falhou - HTTP", [
                'method' => $method,
                'http_code' => $httpCode,
                'curl_error' => $curlError,
                'duration_ms' => $duration,
                'url' => $this->rpcUrl,
            ]);
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger?->error("[BLOCKCHAIN] RPC Call falhou - Parse JSON", [
                'method' => $method,
                'error' => json_last_error_msg(),
                'response_preview' => substr($response, 0, 200),
                'duration_ms' => $duration,
            ]);
            return null;
        }

        $this->logger?->debug("[BLOCKCHAIN] RPC Call sucesso", [
            'method' => $method,
            'has_result' => isset($decoded['result']),
            'result_preview' => isset($decoded['result']) && is_string($decoded['result']) 
                ? substr($decoded['result'], 0, 50) . '...' 
                : (isset($decoded['result']) ? gettype($decoded['result']) : 'null'),
            'duration_ms' => $duration,
        ]);

        return $decoded;
    }
}

