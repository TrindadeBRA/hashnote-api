<?php

declare(strict_types=1);

namespace HashNote\Controller;

use HashNote\Service\MessageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class JobController
{
    public function __construct(
        private readonly MessageService $messageService
    ) {}

    public function tick(Request $request, Response $response): Response
    {
        $processed = $this->messageService->processPendingMessages();
        
        $data = [
            'processed' => $processed,
            'timestamp' => date('c'),
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

