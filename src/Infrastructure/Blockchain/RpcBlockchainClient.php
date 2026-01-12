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
        $response = $this->rpcCall('eth_getTransactionReceipt', [$txHash]);
        
        if (!$response || !isset($response['result']) || $response['result'] === null) {
            return null;
        }

        $receipt = $response['result'];
        
        return [
            'transactionHash' => $receipt['transactionHash'] ?? $txHash,
            'status' => $receipt['status'] ?? '0x0',
            'blockNumber' => $receipt['blockNumber'] ?? null,
            'logs' => $receipt['logs'] ?? [],
        ];
    }

    public function isConfirmed(string $txHash): bool
    {
        $receipt = $this->getReceipt($txHash);
        if (!$receipt) {
            return false;
        }

        // Status 0x1 = sucesso, 0x0 = falha
        if ($receipt['status'] !== '0x1') {
            return false;
        }

        // Se há contrato configurado, verifica se há logs do contrato
        if ($this->contractAddress) {
            $contractAddressLower = strtolower($this->contractAddress);
            foreach ($receipt['logs'] as $log) {
                if (isset($log['address']) && strtolower($log['address']) === $contractAddressLower) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    private function rpcCall(string $method, array $params = []): ?array
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1,
        ];

        $ch = curl_init($this->rpcUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            $this->logger?->error("RPC call failed", [
                'method' => $method,
                'http_code' => $httpCode,
            ]);
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger?->error("RPC response parse error", [
                'method' => $method,
                'error' => json_last_error_msg(),
            ]);
            return null;
        }

        return $decoded;
    }
}

