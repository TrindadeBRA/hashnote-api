<?php

declare(strict_types=1);

use HashNote\Infrastructure\Blockchain\MockBlockchainClient;
use HashNote\Infrastructure\Blockchain\RpcBlockchainClient;
use HashNote\Infrastructure\Blockchain\ServerSignBlockchainClient;
use HashNote\Infrastructure\Persistence\MessageRepository;
use HashNote\Infrastructure\RateLimit\InMemoryRateLimiter;
use HashNote\Service\BlockchainService;
use HashNote\Service\MessageService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

return [
    // Database
    PDO::class => function (ContainerInterface $c) {
        $dbPath = $_ENV['DB_PATH'] ?? 'data/app.sqlite';
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO("sqlite:$dbPath");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    },

    // Logger
    LoggerInterface::class => function (ContainerInterface $c) {
        $logger = new Logger('hashnote');
        $logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));
        return $logger;
    },

    // Repository
    MessageRepository::class => function (ContainerInterface $c) {
        return new MessageRepository($c->get(PDO::class));
    },

    // Blockchain Client
    HashNote\Domain\Blockchain\BlockchainClient::class => function (ContainerInterface $c) {
        $mode = $_ENV['BLOCKCHAIN_MODE'] ?? 'mock';
        
        if ($mode === 'server_sign') {
            $privateKey = $_ENV['BLOCKCHAIN_PRIVATE_KEY'] ?? '';
            if (empty($privateKey)) {
                throw new \RuntimeException(
                    'BLOCKCHAIN_PRIVATE_KEY is required for server_sign mode'
                );
            }
            
            return new ServerSignBlockchainClient(
                $_ENV['BLOCKCHAIN_RPC_URL'] ?? 'http://localhost:8545',
                $privateKey,
                $_ENV['BLOCKCHAIN_CONTRACT_ADDRESS'] ?? null,
                $c->get(LoggerInterface::class)
            );
        }
        
        if ($mode === 'rpc_only') {
            return new RpcBlockchainClient(
                $_ENV['BLOCKCHAIN_RPC_URL'] ?? 'http://localhost:8545',
                $_ENV['BLOCKCHAIN_CONTRACT_ADDRESS'] ?? null,
                $c->get(LoggerInterface::class)
            );
        }
        
        return new MockBlockchainClient($c->get(LoggerInterface::class));
    },

    // Services
    BlockchainService::class => function (ContainerInterface $c) {
        return new BlockchainService(
            $c->get(HashNote\Domain\Blockchain\BlockchainClient::class),
            $c->get(LoggerInterface::class)
        );
    },

    MessageService::class => function (ContainerInterface $c) {
        return new MessageService(
            $c->get(MessageRepository::class),
            $c->get(BlockchainService::class),
            $c->get(LoggerInterface::class)
        );
    },

    // Rate Limiter
    InMemoryRateLimiter::class => function (ContainerInterface $c) {
        $requests = (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 100);
        $window = (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 3600);
        return new InMemoryRateLimiter($requests, $window);
    },

    // Controllers
    HashNote\Controller\HealthController::class => function (ContainerInterface $c) {
        return new HashNote\Controller\HealthController();
    },

    HashNote\Controller\MessageController::class => function (ContainerInterface $c) {
        return new HashNote\Controller\MessageController(
            $c->get(MessageService::class),
            $c->get(LoggerInterface::class)
        );
    },

    HashNote\Controller\JobController::class => function (ContainerInterface $c) {
        return new HashNote\Controller\JobController(
            $c->get(MessageService::class)
        );
    },
];

