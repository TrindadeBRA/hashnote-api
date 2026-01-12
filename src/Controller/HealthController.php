<?php

declare(strict_types=1);

namespace HashNote\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthController
{
    public function check(Request $request, Response $response): Response
    {
        $data = [
            'status' => 'ok',
            'version' => $_ENV['APP_VERSION'] ?? '1.0.0',
            'blockchain_mode' => $_ENV['BLOCKCHAIN_MODE'] ?? 'mock',
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

