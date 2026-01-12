<?php

declare(strict_types=1);

namespace HashNote\Infrastructure\RateLimit;

class InMemoryRateLimiter
{
    private array $requests = [];

    public function __construct(
        private readonly int $maxRequests,
        private readonly int $windowSeconds
    ) {}

    public function isAllowed(string $identifier): bool
    {
        $now = time();
        $key = $identifier;
        
        if (!isset($this->requests[$key])) {
            $this->requests[$key] = [];
        }

        // Remove requisições antigas
        $this->requests[$key] = array_filter(
            $this->requests[$key],
            fn($timestamp) => ($now - $timestamp) < $this->windowSeconds
        );

        // Verifica limite
        if (count($this->requests[$key]) >= $this->maxRequests) {
            return false;
        }

        // Adiciona requisição atual
        $this->requests[$key][] = $now;
        
        return true;
    }

    public function getRemaining(string $identifier): int
    {
        $now = time();
        $key = $identifier;
        
        if (!isset($this->requests[$key])) {
            return $this->maxRequests;
        }

        $this->requests[$key] = array_filter(
            $this->requests[$key],
            fn($timestamp) => ($now - $timestamp) < $this->windowSeconds
        );

        return max(0, $this->maxRequests - count($this->requests[$key]));
    }
}

