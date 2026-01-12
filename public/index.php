<?php

declare(strict_types=1);

use HashNote\App\AppFactory;
use DI\ContainerBuilder;

require_once __DIR__ . '/../vendor/autoload.php';

// Carrega variáveis de ambiente (se .env existir)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    } catch (Exception $e) {
        // Log do erro mas continua (usa valores padrão)
        error_log("Aviso: Erro ao carregar .env: " . $e->getMessage());
    }
}

// Configura dependências e cria container DI
$builder = new ContainerBuilder();
$definitions = require __DIR__ . '/../config/dependencies.php';
$builder->addDefinitions($definitions);
$container = $builder->build();

// Cria aplicação
$app = AppFactory::create($container);

// Disponibiliza app globalmente para rotas e middleware
$GLOBALS['app'] = $app;

// Carrega rotas
require_once __DIR__ . '/../config/routes.php';

// Middleware
require_once __DIR__ . '/../config/middleware.php';

// Executa aplicação
$app->run();