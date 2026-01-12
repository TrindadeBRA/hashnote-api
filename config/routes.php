<?php

declare(strict_types=1);

use HashNote\Controller\HealthController;
use HashNote\Controller\JobController;
use HashNote\Controller\MessageController;
use Slim\App;

/** @var App $app */
$app = $GLOBALS['app'];
$container = $app->getContainer();

// Health check
$app->get('/health', function ($request, $response) use ($container) {
    $controller = $container->get(HealthController::class);
    return $controller->check($request, $response);
});

// Documentation
$app->get('/docs', function ($request, $response) {
    // Usa CDN do unpkg para Swagger UI (mais confiável)
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>HashNote API - Swagger UI</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin: 0;
            background: #fafafa;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            window.ui = SwaggerUIBundle({
                url: "/openapi.yaml",
                dom_id: "#swagger-ui",
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
            });
        };
    </script>
</body>
</html>';
    
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

$app->get('/openapi.yaml', function ($request, $response) {
    $yamlPath = __DIR__ . '/../swagger/openapi.yaml';
    
    if (!file_exists($yamlPath)) {
        $response->getBody()->write('OpenAPI spec not found');
        return $response->withStatus(404);
    }
    
    $yaml = file_get_contents($yamlPath);
    $response->getBody()->write($yaml);
    return $response->withHeader('Content-Type', 'application/x-yaml');
});

// API v1
$app->group('/v1', function ($group) use ($container) {
    // Messages
    $group->post('/messages', function ($request, $response) use ($container) {
        $controller = $container->get(MessageController::class);
        return $controller->create($request, $response);
    });
    
    $group->get('/messages/{id}', function ($request, $response, $args) use ($container) {
        $controller = $container->get(MessageController::class);
        return $controller->get($request, $response, $args);
    });
    
    $group->get('/messages/{id}/verify', function ($request, $response, $args) use ($container) {
        $controller = $container->get(MessageController::class);
        return $controller->verify($request, $response, $args);
    });
    
    // Jobs (protegido por middleware)
    $group->post('/jobs/tick', function ($request, $response) use ($container) {
        $controller = $container->get(JobController::class);
        return $controller->tick($request, $response);
    });
});

