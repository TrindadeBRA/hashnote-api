<?php

declare(strict_types=1);

/**
 * Script para verificar saldo de uma wallet Ethereum
 * 
 * Uso: php scripts/check-balance.php [endereco]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Carrega .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$rpcUrl = $_ENV['BLOCKCHAIN_RPC_URL'] ?? 'http://localhost:8545';
$network = $_ENV['BLOCKCHAIN_NETWORK'] ?? 'sepolia';

// Pega endereço do argumento ou deriva da chave privada
$address = $argv[1] ?? null;

if (!$address) {
    // Tenta derivar do .env
    $privateKey = $_ENV['BLOCKCHAIN_PRIVATE_KEY'] ?? null;
    
    if ($privateKey) {
        $cleanKey = str_starts_with($privateKey, '0x') 
            ? substr($privateKey, 2) 
            : $privateKey;
        
        try {
            $addressGenerator = new \kornrunner\Ethereum\Address($cleanKey);
            $address = $addressGenerator->get();
            echo "📍 Endereço derivado da chave privada do .env\n";
        } catch (\Exception $e) {
            echo "❌ Erro ao derivar endereço: " . $e->getMessage() . "\n";
            echo "\nUso: php scripts/check-balance.php 0xSEU_ENDERECO\n";
            exit(1);
        }
    } else {
        echo "❌ Endereço não fornecido e BLOCKCHAIN_PRIVATE_KEY não configurado\n";
        echo "\nUso: php scripts/check-balance.php 0xSEU_ENDERECO\n";
        exit(1);
    }
}

// Remove 0x se existir
$address = str_starts_with($address, '0x') ? substr($address, 2) : $address;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "💰 Verificação de Saldo\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "📍 Endereço: 0x$address\n";
echo "🌐 Rede: $network\n";
echo "🔗 RPC: $rpcUrl\n\n";

// Faz chamada RPC para obter saldo
$data = [
    'jsonrpc' => '2.0',
    'method' => 'eth_getBalance',
    'params' => ['0x' . $address, 'latest'],
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

if ($httpCode !== 200 || !$response) {
    echo "❌ Erro ao consultar saldo (HTTP $httpCode)\n";
    exit(1);
}

$result = json_decode($response, true);

if (isset($result['error'])) {
    echo "❌ Erro RPC: " . ($result['error']['message'] ?? 'Unknown error') . "\n";
    exit(1);
}

if (!isset($result['result'])) {
    echo "❌ Resposta inválida do RPC\n";
    exit(1);
}

// Converte wei para ETH
$wei = gmp_init($result['result'], 16);
$eth = gmp_strval($wei) / 1e18;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "💵 SALDO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

printf("   Wei:  %s\n", gmp_strval($wei));
printf("   ETH:  %.8f ETH\n", $eth);

if ($eth < 0.001) {
    echo "\n⚠️  Saldo muito baixo! Você precisa de ETH para pagar gas fees.\n\n";
    echo "📋 Para obter ETH de teste (Sepolia):\n";
    echo "   1. https://sepoliafaucet.com/\n";
    echo "   2. https://faucet.sepolia.dev/\n";
    echo "   3. https://www.alchemy.com/faucets/ethereum-sepolia\n";
    echo "   4. https://www.infura.io/faucet/sepolia\n\n";
    echo "   Cole o endereço: 0x$address\n";
} elseif ($eth < 0.01) {
    echo "\n✅ Saldo suficiente para alguns testes (recomendado: 0.01+ ETH)\n";
} else {
    echo "\n✅ Saldo suficiente para testes!\n";
}

echo "\n🔍 Ver no explorer:\n";
if ($network === 'sepolia') {
    echo "   https://sepolia.etherscan.io/address/0x$address\n";
} elseif ($network === 'mainnet') {
    echo "   https://etherscan.io/address/0x$address\n";
}

echo "\n";

