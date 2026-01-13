<?php

declare(strict_types=1);

namespace HashNote\Controller;

use HashNote\Service\MessageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class MessageController
{
    public function __construct(
        private readonly MessageService $messageService,
        private readonly LoggerInterface $logger
    ) {}

    public function create(Request $request, Response $response): Response
    {
        $startTime = microtime(true);
        $data = $request->getParsedBody();
        
        $this->logger->info("[HTTP] POST /v1/messages - Requisição recebida", [
            'has_message' => isset($data['message']),
            'message_type' => isset($data['message']) ? gettype($data['message']) : null,
            'message_length' => isset($data['message']) && is_string($data['message']) ? strlen($data['message']) : null,
        ]);
        
        if (!isset($data['message']) || !is_string($data['message'])) {
            $this->logger->warning("[HTTP] POST /v1/messages - Validação falhou", [
                'has_message' => isset($data['message']),
                'message_type' => isset($data['message']) ? gettype($data['message']) : null,
            ]);
            $response->getBody()->write(json_encode([
                'error' => 'Message is required and must be a string',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $message = $this->messageService->createMessage($data['message']);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->info("[HTTP] POST /v1/messages - Sucesso", [
                'id' => $message->id,
                'tx_hash' => $message->txHash,
                'status' => $message->status,
                'duration_ms' => $duration,
            ]);
            
            $response->getBody()->write(json_encode($message->toArray()));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->warning("[HTTP] POST /v1/messages - Erro de validação", [
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage(),
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->error("[HTTP] POST /v1/messages - Erro de runtime", [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => $duration,
            ]);
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage(),
            ]));
            return $response->withStatus(501)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->error("[HTTP] POST /v1/messages - Erro interno", [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => $duration,
            ]);
            $response->getBody()->write(json_encode([
                'error' => 'Internal server error',
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $startTime = microtime(true);
        $id = $args['id'] ?? null;
        
        $this->logger->info("[HTTP] GET /v1/messages/{id} - Requisição recebida", [
            'id' => $id,
        ]);
        
        if (!$id) {
            $this->logger->warning("[HTTP] GET /v1/messages/{id} - ID não fornecido");
            $response->getBody()->write(json_encode([
                'error' => 'Message ID is required',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $message = $this->messageService->getMessage($id);
        
        if (!$message) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info("[HTTP] GET /v1/messages/{id} - Mensagem não encontrada", [
                'id' => $id,
                'duration_ms' => $duration,
            ]);
            $response->getBody()->write(json_encode([
                'error' => 'Message not found',
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $this->logger->info("[HTTP] GET /v1/messages/{id} - Sucesso", [
            'id' => $id,
            'status' => $message->status,
            'tx_hash' => $message->txHash,
            'duration_ms' => $duration,
        ]);

        $response->getBody()->write(json_encode($message->toArray()));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function verify(Request $request, Response $response, array $args): Response
    {
        $startTime = microtime(true);
        $id = $args['id'] ?? null;
        
        $this->logger->info("[HTTP] GET /v1/messages/{id}/verify - Requisição recebida", [
            'id' => $id,
        ]);
        
        if (!$id) {
            $this->logger->warning("[HTTP] GET /v1/messages/{id}/verify - ID não fornecido");
            $response->getBody()->write(json_encode([
                'error' => 'Message ID is required',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $result = $this->messageService->verifyMessage($id);
        
        if (!$result) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info("[HTTP] GET /v1/messages/{id}/verify - Mensagem não encontrada", [
                'id' => $id,
                'duration_ms' => $duration,
            ]);
            $response->getBody()->write(json_encode([
                'error' => 'Message not found',
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $this->logger->info("[HTTP] GET /v1/messages/{id}/verify - Sucesso", [
            'id' => $id,
            'valid' => $result['valid'] ?? false,
            'status' => $result['status'] ?? null,
            'tx_hash' => $result['tx_hash'] ?? null,
            'duration_ms' => $duration,
        ]);

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

