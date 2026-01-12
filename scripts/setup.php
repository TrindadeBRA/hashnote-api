<?php

declare(strict_types=1);

/**
 * Script de setup do banco de dados SQLite
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carrega .env se existir, mas não falha se não existir
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    } catch (Exception $e) {
        // Ignora erros de parsing do .env e usa valores padrão
        error_log("Aviso: Erro ao carregar .env: " . $e->getMessage());
    }
}

$dbPath = $_ENV['DB_PATH'] ?? 'data/app.sqlite';
$dir = dirname($dbPath);

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    echo "Diretório criado: $dir\n";
}

$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Cria tabela messages
$pdo->exec("
    CREATE TABLE IF NOT EXISTS messages (
        id TEXT PRIMARY KEY,
        message TEXT NOT NULL,
        msg_hash TEXT NOT NULL,
        tx_hash TEXT NULL,
        status TEXT NOT NULL CHECK(status IN ('pending', 'confirmed', 'failed')),
        block_number INTEGER NULL,
        confirmed_at TEXT NULL,
        created_at TEXT NOT NULL
    )
");

// Cria índices
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tx_hash ON messages(tx_hash)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_status ON messages(status)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON messages(created_at)");

echo "Banco de dados configurado com sucesso!\n";
echo "Arquivo: $dbPath\n";

