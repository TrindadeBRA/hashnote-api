<?php

declare(strict_types=1);

/**
 * Script para gerar uma nova wallet Ethereum
 * 
 * Uso: php scripts/generate-wallet.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== Gerador de Wallet Ethereum ===\n\n";

try {
    // Gera uma nova wallet aleatória usando kornrunner/ethereum-address
    $addressGenerator = new \kornrunner\Ethereum\Address();
    $privateKey = $addressGenerator->getPrivateKey();
    $address = $addressGenerator->get();
    
    echo "✅ Wallet gerada com sucesso!\n\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📝 INFORMAÇÕES DA WALLET\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "🔑 Chave Privada (SEM 0x):\n";
    echo "   $privateKey\n\n";
    
    echo "📍 Endereço da Wallet:\n";
    echo "   0x$address\n\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "⚠️  ATENÇÃO - SEGURANÇA\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "1. NUNCA compartilhe sua chave privada\n";
    echo "2. NUNCA commite a chave privada no Git\n";
    echo "3. Use apenas em testnet (Sepolia) para testes\n";
    echo "4. Para produção, use um gerenciador de segredos (AWS Secrets, etc)\n\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📋 PRÓXIMOS PASSOS\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "1. Copie a chave privada acima (SEM 0x)\n";
    echo "2. Adicione no seu .env:\n";
    echo "   BLOCKCHAIN_PRIVATE_KEY=$privateKey\n\n";
    
    echo "3. Obtenha ETH de teste (Sepolia):\n";
    echo "   - Acesse: https://sepoliafaucet.com/\n";
    echo "   - Cole o endereço: 0x$address\n";
    echo "   - Ou use: https://faucet.sepolia.dev/\n";
    echo "   - Ou use: https://www.alchemy.com/faucets/ethereum-sepolia\n\n";
    
    echo "4. Verifique o saldo em:\n";
    echo "   https://sepolia.etherscan.io/address/0x$address\n\n";
    
    echo "5. Após receber ETH, teste a API:\n";
    echo "   curl -X POST http://localhost:8000/v1/messages \\\n";
    echo "     -H 'Content-Type: application/json' \\\n";
    echo "     -d '{\"message\": \"Teste da API\"}'\n\n";
    
    // Salva em arquivo temporário (opcional, para facilitar)
    $tempFile = sys_get_temp_dir() . '/hashnote-wallet-' . date('Y-m-d-His') . '.txt';
    file_put_contents($tempFile, 
        "Wallet gerada em: " . date('Y-m-d H:i:s') . "\n\n" .
        "Chave Privada (SEM 0x):\n$privateKey\n\n" .
        "Endereço:\n0x$address\n\n" .
        "⚠️ MANTENHA ESTE ARQUIVO SEGURO E DELETE APÓS USO!\n"
    );
    
    echo "💾 Informações salvas temporariamente em:\n";
    echo "   $tempFile\n";
    echo "   (DELETE este arquivo após copiar as informações!)\n\n";
    
} catch (\Exception $e) {
    echo "❌ Erro ao gerar wallet: " . $e->getMessage() . "\n";
    echo "Certifique-se de que a biblioteca kornrunner/ethereum está instalada.\n";
    exit(1);
}

