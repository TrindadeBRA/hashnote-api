<?php

declare(strict_types=1);

use HashNote\Infrastructure\RateLimit\InMemoryRateLimiter;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;

/** @var App $app */
$app = $GLOBALS['app'];

// Rate limiting middleware
$app->add(function (Request $request, RequestHandler $handler) use ($app): Response {
    // Aplica rate limit apenas em rotas da API
    $path = $request->getUri()->getPath();
    if (strpos($path, '/v1/') === 0 && strpos($path, '/v1/jobs/') !== 0) {
        $container = $app->getContainer();
        /** @var InMemoryRateLimiter $rateLimiter */
        $rateLimiter = $container->get(InMemoryRateLimiter::class);
        
        // Identifica cliente por IP
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        
        if (!$rateLimiter->isAllowed($ip)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Rate limit exceeded',
                'remaining' => 0,
            ]));
            return $response
                ->withStatus(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-RateLimit-Limit', (string)$rateLimiter->getRemaining($ip))
                ->withHeader('Retry-After', '3600');
        }
        
        $response = $handler->handle($request);
        $remaining = $rateLimiter->getRemaining($ip);
        
        return $response
            ->withHeader('X-RateLimit-Remaining', (string)$remaining);
    }
    
    return $handler->handle($request);
});

// Job token middleware (para /v1/jobs/*)
$app->add(function (Request $request, RequestHandler $handler): Response {
    $path = $request->getUri()->getPath();
    
    if (strpos($path, '/v1/jobs/') === 0) {
        $token = $request->getHeaderLine('X-Job-Token');
        $expectedToken = $_ENV['JOB_TOKEN'] ?? 'change-me-in-production';
        
        if ($token !== $expectedToken) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Unauthorized',
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }
    
    return $handler->handle($request);
});

// JSON parsing middleware
$app->addBodyParsingMiddleware();

// Error handling middleware
$app->addErrorMiddleware(true, true, true);

