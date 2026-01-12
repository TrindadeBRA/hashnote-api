<?php

declare(strict_types=1);

namespace HashNote\Domain\Blockchain;

interface BlockchainClient
{
    /**
     * Registra uma mensagem na blockchain
     * @param string $msgHash Hash da mensagem (0x...)
     * @return string Transaction hash (0x...)
     */
    public function registerMessage(string $msgHash): string;

    /**
     * Obtém o receipt de uma transação
     * @param string $txHash Transaction hash
     * @return array|null Receipt ou null se não encontrado
     */
    public function getReceipt(string $txHash): ?array;

    /**
     * Verifica se uma transação está confirmada
     * @param string $txHash Transaction hash
     * @return bool
     */
    public function isConfirmed(string $txHash): bool;
}

