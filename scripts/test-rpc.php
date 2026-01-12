<?php

/**
 * Script para testar conexão RPC com Ethereum
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$rpcUrl = $_ENV['BLOCKCHAIN_RPC_URL'] ?? 'http://localhost:8545';

echo "Testando conexão RPC: $rpcUrl\n\n";

// Teste 1: eth_blockNumber (obter último bloco)
$data = [
    'jsonrpc' => '2.0',
    'method' => 'eth_blockNumber',
    'params' => [],
    'id' => 1,
];

$ch = curl_init($rpcUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($data),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $result = json_decode($response, true);
    if (isset($result['result'])) {
        $blockNumber = hexdec($result['result']);
        echo "✅ Conexão OK!\n";
        echo "Último bloco: $blockNumber\n";
    } else {
        echo "❌ Erro na resposta: " . json_encode($result) . "\n";
    }
} else {
    echo "❌ Erro HTTP: $httpCode\n";
    echo "Resposta: $response\n";
}

// Teste 2: eth_chainId (identificar rede)
$data = [
    'jsonrpc' => '2.0',
    'method' => 'eth_chainId',
    'params' => [],
    'id' => 2,
];

$ch = curl_init($rpcUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($data),
]);

$response = curl_exec($ch);
$result = json_decode($response, true);

if (isset($result['result'])) {
    $chainId = hexdec($result['result']);
    $networks = [
        1 => 'Ethereum Mainnet',
        11155111 => 'Sepolia Testnet',
        5 => 'Goerli Testnet',
    ];
    $networkName = $networks[$chainId] ?? "Unknown (Chain ID: $chainId)";
    echo "Rede: $networkName\n";
}

echo "\n";

