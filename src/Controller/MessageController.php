<?php

declare(strict_types=1);

namespace HashNote\Controller;

use HashNote\Service\MessageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MessageController
{
    public function __construct(
        private readonly MessageService $messageService
    ) {}

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        if (!isset($data['message']) || !is_string($data['message'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Message is required and must be a string',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $message = $this->messageService->createMessage($data['message']);
            
            $response->getBody()->write(json_encode($message->toArray()));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage(),
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage(),
            ]));
            return $response->withStatus(501)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Internal server error',
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;
        
        if (!$id) {
            $response->getBody()->write(json_encode([
                'error' => 'Message ID is required',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $message = $this->messageService->getMessage($id);
        
        if (!$message) {
            $response->getBody()->write(json_encode([
                'error' => 'Message not found',
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($message->toArray()));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function verify(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;
        
        if (!$id) {
            $response->getBody()->write(json_encode([
                'error' => 'Message ID is required',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $result = $this->messageService->verifyMessage($id);
        
        if (!$result) {
            $response->getBody()->write(json_encode([
                'error' => 'Message not found',
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

