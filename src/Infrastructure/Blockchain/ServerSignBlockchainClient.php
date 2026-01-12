<?php

declare(strict_types=1);

namespace HashNote\Infrastructure\Blockchain;

use HashNote\Domain\Blockchain\BlockchainClient;
use Psr\Log\LoggerInterface;

class ServerSignBlockchainClient implements BlockchainClient
{
    private ?string $fromAddress = null;

    public function __construct(
        private readonly string $rpcUrl,
        private readonly string $privateKey,
        private readonly ?string $contractAddress = null,
        private readonly ?LoggerInterface $logger = null
    ) {
        // Remove 0x do início da chave privada se existir
        $cleanKey = str_starts_with($this->privateKey, '0x') 
            ? substr($this->privateKey, 2) 
            : $this->privateKey;

        // Valida chave privada
        if (strlen($cleanKey) !== 64 || !ctype_xdigit($cleanKey)) {
            throw new \InvalidArgumentException("Invalid private key format");
        }

        // Deriva endereço da chave privada
        try {
            $this->fromAddress = $this->deriveAddressFromPrivateKey($cleanKey);
            
            $this->logger?->info("ServerSignBlockchainClient initialized", [
                'rpc_url' => $this->rpcUrl,
                'from_address' => $this->fromAddress,
                'has_contract' => $this->contractAddress !== null,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error("Failed to initialize blockchain client", [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException("Failed to initialize blockchain client: " . $e->getMessage());
        }
    }

    public function registerMessage(string $msgHash): string
    {
        try {
            // 1. Obter nonce atual
            $nonce = $this->getNonce($this->fromAddress);
            
            // 2. Obter gas price
            $gasPrice = $this->getGasPrice();
            
            // 3. Estimar gas (se usar contrato) ou usar valor padrão
            $gasLimit = $this->contractAddress 
                ? $this->estimateGas($msgHash, $nonce, $gasPrice)
                : '0x5208'; // 21000 para transfer simples
            
            // 4. Criar transação
            $transaction = [
                'nonce' => '0x' . dechex($nonce),
                'gasPrice' => $gasPrice,
                'gas' => $gasLimit,
                'to' => $this->contractAddress ?? $this->fromAddress, // Se não tem contrato, envia para si mesmo
                'value' => '0x0',
                'data' => $this->contractAddress 
                    ? $this->encodeContractCall($msgHash)
                    : $msgHash, // Se não tem contrato, envia msg_hash como data
                'chainId' => $this->getChainId(),
            ];

            // 5. Assinar transação
            $signedTx = $this->signTransaction($transaction);
            
            // 6. Enviar transação
            $txHash = $this->sendRawTransaction($signedTx);
            
            $this->logger?->info("Message registered on blockchain", [
                'msg_hash' => $msgHash,
                'tx_hash' => $txHash,
                'nonce' => $nonce,
            ]);

            return $txHash;
        } catch (\Exception $e) {
            $this->logger?->error("Failed to register message", [
                'msg_hash' => $msgHash,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getReceipt(string $txHash): ?array
    {
        // Reutiliza lógica do RpcBlockchainClient
        $rpcClient = new RpcBlockchainClient(
            $this->rpcUrl,
            $this->contractAddress,
            $this->logger
        );
        
        return $rpcClient->getReceipt($txHash);
    }

    public function isConfirmed(string $txHash): bool
    {
        // Reutiliza lógica do RpcBlockchainClient
        $rpcClient = new RpcBlockchainClient(
            $this->rpcUrl,
            $this->contractAddress,
            $this->logger
        );
        
        return $rpcClient->isConfirmed($txHash);
    }

    private function getNonce(string $address): int
    {
        $response = $this->rpcCall('eth_getTransactionCount', [$address, 'latest']);
        
        if (!$response || !isset($response['result'])) {
            throw new \RuntimeException("Failed to get nonce");
        }
        
        return (int)hexdec(substr($response['result'], 2));
    }

    private function getGasPrice(): string
    {
        $response = $this->rpcCall('eth_gasPrice', []);
        
        if ($response && isset($response['result'])) {
            return $response['result'];
        }
        
        // Fallback: gas price padrão para testnet (20 gwei)
        return '0x4a817c800'; // 20 gwei em hex
    }

    private function estimateGas(string $msgHash, int $nonce, string $gasPrice): string
    {
        $transaction = [
            'from' => $this->fromAddress,
            'to' => $this->contractAddress,
            'data' => $this->encodeContractCall($msgHash),
            'gasPrice' => $gasPrice,
        ];

        $response = $this->rpcCall('eth_estimateGas', [$transaction]);
        
        if ($response && isset($response['result'])) {
            // Adiciona 20% de margem
            $estimated = hexdec(substr($response['result'], 2));
            $withMargin = (int)($estimated * 1.2);
            return '0x' . dechex($withMargin);
        }
        
        // Fallback: 100k gas
        return '0x186a0';
    }

    private function encodeContractCall(string $msgHash): string
    {
        // Se não tem contrato, retorna msg_hash como data
        if (!$this->contractAddress) {
            return $msgHash;
        }
        
        // Para contrato, precisaria do ABI e função específica
        // Por enquanto, retorna msg_hash (será implementado quando houver contrato)
        // Função hash: keccak256("registerMessage(bytes32)") = 0x...
        // Por simplicidade do MVP, vamos usar apenas o msg_hash
        return $msgHash;
    }

    private function signTransaction(array $transaction): string
    {
        // Remove 0x se existir da chave privada
        $cleanKey = str_starts_with($this->privateKey, '0x') 
            ? substr($this->privateKey, 2) 
            : $this->privateKey;
        
        try {
            // Converte valores hex para decimal para a biblioteca
            $nonce = hexdec(substr($transaction['nonce'], 2));
            $gasPrice = hexdec(substr($transaction['gasPrice'], 2));
            $gas = hexdec(substr($transaction['gas'], 2));
            $value = hexdec(substr($transaction['value'], 2));
            $chainId = hexdec(substr($transaction['chainId'], 2));
            
            // Remove 0x do endereço e data se existir
            $to = str_starts_with($transaction['to'], '0x') 
                ? substr($transaction['to'], 2) 
                : $transaction['to'];
            $data = str_starts_with($transaction['data'], '0x') 
                ? substr($transaction['data'], 2) 
                : $transaction['data'];
            
            // Usa biblioteca kornrunner/ethereum-offline-raw-tx para assinar
            $transactionObj = new \kornrunner\Ethereum\Transaction(
                (string)$nonce,
                (string)$gasPrice,
                (string)$gas,
                $to,
                (string)$value,
                $data
            );
            
            // Assina transação usando getRaw() (método público que chama sign() internamente)
            $signed = $transactionObj->getRaw($cleanKey, (int)$chainId);
            
            return $signed;
        } catch (\Exception $e) {
            $this->logger?->error("Failed to sign transaction", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException("Failed to sign transaction: " . $e->getMessage(), 0, $e);
        }
    }

    private function sendRawTransaction(string $signedTx): string
    {
        $response = $this->rpcCall('eth_sendRawTransaction', ['0x' . $signedTx]);
        
        if (!$response || !isset($response['result'])) {
            $error = $response['error']['message'] ?? 'Unknown error';
            throw new \RuntimeException("Failed to send transaction: $error");
        }
        
        return $response['result'];
    }

    private function deriveAddressFromPrivateKey(string $privateKey): string
    {
        try {
            // Usa biblioteca kornrunner/ethereum-address para derivar endereço
            $addressGenerator = new \kornrunner\Ethereum\Address($privateKey);
            $address = $addressGenerator->get();
            return '0x' . $address;
        } catch (\Exception $e) {
            $this->logger?->error("Failed to derive address from private key", [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException("Failed to derive address: " . $e->getMessage(), 0, $e);
        }
    }

    private function getChainId(): string
    {
        // Obtém Chain ID da rede
        $response = $this->rpcCall('eth_chainId', []);
        
        if ($response && isset($response['result'])) {
            return $response['result'];
        }
        
        // Fallback baseado na rede configurada
        $network = $_ENV['BLOCKCHAIN_NETWORK'] ?? 'sepolia';
        return match($network) {
            'sepolia' => '0xaa36a7', // 11155111
            'mainnet' => '0x1', // 1
            default => '0x1',
        };
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

        if (isset($decoded['error'])) {
            $this->logger?->error("RPC error", [
                'method' => $method,
                'error' => $decoded['error'],
            ]);
            return null;
        }

        return $decoded;
    }
}

