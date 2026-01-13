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
        $this->logger?->info("[BLOCKCHAIN] Iniciando registro de mensagem", [
            'msg_hash' => $msgHash,
            'from_address' => $this->fromAddress,
            'has_contract' => $this->contractAddress !== null,
        ]);

        try {
            // 1. Obter nonce atual
            $this->logger?->info("[BLOCKCHAIN] Step 1: Obtendo nonce", [
                'address' => $this->fromAddress,
            ]);
            $nonce = $this->getNonce($this->fromAddress);
            $this->logger?->info("[BLOCKCHAIN] Step 1: Nonce obtido", [
                'nonce' => $nonce,
            ]);
            
            // 2. Obter gas price
            $this->logger?->info("[BLOCKCHAIN] Step 2: Obtendo gas price");
            $gasPrice = $this->getGasPrice();
            $this->logger?->info("[BLOCKCHAIN] Step 2: Gas price obtido", [
                'gas_price' => $gasPrice,
            ]);
            
            // 3. Estimar gas (se usar contrato) ou usar valor padrão
            $gasLimit = $this->contractAddress 
                ? $this->estimateGas($msgHash, $nonce, $gasPrice)
                : '0x5208'; // 21000 para transfer simples
            $this->logger?->info("[BLOCKCHAIN] Step 3: Gas limit definido", [
                'gas_limit' => $gasLimit,
                'estimated' => $this->contractAddress !== null,
            ]);
            
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
            $this->logger?->info("[BLOCKCHAIN] Step 4: Transação criada", [
                'nonce' => $transaction['nonce'],
                'to' => $transaction['to'],
                'gas_price' => $gasPrice,
                'gas_limit' => $gasLimit,
                'data_preview' => substr($transaction['data'], 0, 50) . '...',
            ]);

            // 5. Assinar transação
            $this->logger?->info("[BLOCKCHAIN] Step 5: Assinando transação");
            $signedTx = $this->signTransaction($transaction);
            $this->logger?->info("[BLOCKCHAIN] Step 5: Transação assinada", [
                'signed_tx_preview' => substr($signedTx, 0, 50) . '...',
                'signed_tx_length' => strlen($signedTx),
            ]);
            
            // 6. Enviar transação
            $this->logger?->info("[BLOCKCHAIN] Step 6: Enviando transação para blockchain");
            $txHash = $this->sendRawTransaction($signedTx);
            $this->logger?->info("[BLOCKCHAIN] Step 6: Transação enviada com sucesso", [
                'tx_hash' => $txHash,
            ]);
            
            $this->logger?->info("[BLOCKCHAIN] Mensagem registrada na blockchain", [
                'msg_hash' => $msgHash,
                'tx_hash' => $txHash,
                'nonce' => $nonce,
                'gas_price' => $gasPrice,
                'gas_limit' => $gasLimit,
            ]);

            return $txHash;
        } catch (\Exception $e) {
            $this->logger?->error("[BLOCKCHAIN] Falha ao registrar mensagem", [
                'msg_hash' => $msgHash,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
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
        // Usa 'pending' para incluir transações pendentes na mempool
        // Isso garante que cada nova transação use um nonce único
        $this->logger?->info("[BLOCKCHAIN] RPC: eth_getTransactionCount", [
            'address' => $address,
            'block' => 'pending',
        ]);
        
        $response = $this->rpcCall('eth_getTransactionCount', [$address, 'pending']);
        
        if (!$response || !isset($response['result'])) {
            $this->logger?->error("[BLOCKCHAIN] RPC: eth_getTransactionCount falhou", [
                'address' => $address,
                'response' => $response,
            ]);
            throw new \RuntimeException("Failed to get nonce");
        }
        
        $nonce = (int)hexdec(substr($response['result'], 2));
        $this->logger?->info("[BLOCKCHAIN] RPC: eth_getTransactionCount resposta", [
            'address' => $address,
            'nonce_hex' => $response['result'],
            'nonce_dec' => $nonce,
            'block' => 'pending',
            'note' => 'Inclui transações pendentes na mempool',
        ]);
        
        return $nonce;
    }

    private function getGasPrice(): string
    {
        $this->logger?->info("[BLOCKCHAIN] RPC: eth_gasPrice");
        
        $response = $this->rpcCall('eth_gasPrice', []);
        
        // Gas price mínimo para Sepolia (2 gwei) para garantir que transações sejam aceitas
        $minGasPriceGwei = 2.0;
        $minGasPriceWei = (int)($minGasPriceGwei * 1e9);
        $minGasPriceHex = '0x' . dechex($minGasPriceWei);
        
        if ($response && isset($response['result'])) {
            $gasPrice = $response['result'];
            $gasPriceWei = hexdec(substr($gasPrice, 2));
            $gasPriceGwei = $gasPriceWei / 1e9;
            
            // Se o gas price da rede for menor que o mínimo, usa o mínimo
            if ($gasPriceWei < $minGasPriceWei) {
                $this->logger?->info("[BLOCKCHAIN] RPC: eth_gasPrice resposta (abaixo do mínimo, usando mínimo)", [
                    'gas_price_rede_hex' => $gasPrice,
                    'gas_price_rede_gwei' => round($gasPriceGwei, 2),
                    'gas_price_minimo_hex' => $minGasPriceHex,
                    'gas_price_minimo_gwei' => $minGasPriceGwei,
                    'gas_price_final_hex' => $minGasPriceHex,
                    'gas_price_final_gwei' => $minGasPriceGwei,
                ]);
                return $minGasPriceHex;
            }
            
            $this->logger?->info("[BLOCKCHAIN] RPC: eth_gasPrice resposta", [
                'gas_price_hex' => $gasPrice,
                'gas_price_gwei' => round($gasPriceGwei, 2),
            ]);
            return $gasPrice;
        }
        
        // Fallback: gas price mínimo para testnet (2 gwei)
        $this->logger?->warning("[BLOCKCHAIN] RPC: eth_gasPrice falhou, usando mínimo", [
            'fallback_gas_price' => $minGasPriceHex,
            'fallback_gwei' => $minGasPriceGwei,
        ]);
        return $minGasPriceHex;
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
        $signedTxWithPrefix = '0x' . $signedTx;
        $this->logger?->info("[BLOCKCHAIN] RPC: eth_sendRawTransaction", [
            'signed_tx_length' => strlen($signedTxWithPrefix),
            'signed_tx_preview' => substr($signedTxWithPrefix, 0, 50) . '...',
        ]);
        
        $response = $this->rpcCall('eth_sendRawTransaction', [$signedTxWithPrefix]);
        
        if (!$response || !isset($response['result'])) {
            $error = $response['error']['message'] ?? 'Unknown error';
            $errorCode = $response['error']['code'] ?? 'unknown';
            $this->logger?->error("[BLOCKCHAIN] RPC: eth_sendRawTransaction falhou", [
                'error_code' => $errorCode,
                'error_message' => $error,
                'error_full' => json_encode($response['error'] ?? []),
            ]);
            throw new \RuntimeException("Failed to send transaction: $error");
        }
        
        $txHash = $response['result'];
        $this->logger?->info("[BLOCKCHAIN] RPC: eth_sendRawTransaction sucesso", [
            'tx_hash' => $txHash,
        ]);
        
        return $txHash;
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

        if (isset($decoded['error'])) {
            $errorCode = $decoded['error']['code'] ?? 'unknown';
            $errorMessage = $decoded['error']['message'] ?? json_encode($decoded['error']);
            
            $this->logger?->error("[BLOCKCHAIN] RPC Call erro retornado", [
                'method' => $method,
                'params' => $params,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'error_full' => json_encode($decoded['error']),
                'duration_ms' => $duration,
            ]);
            // Retorna o array com o erro para que o chamador possa tratá-lo
            return $decoded;
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

