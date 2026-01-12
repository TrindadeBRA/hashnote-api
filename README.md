# HashNote API

API minimalista para registrar mensagens curtas na blockchain (POC/MVP).

## Stack Tecnológica

- **PHP 8.2+**
- **Slim Framework 4** + **PHP-DI** (dependency injection)
- **SQLite** (PDO, sem ORM pesado)
- **OpenAPI 3.0** (Swagger UI)
- Arquitetura: Routes → Controllers → Services → Repositories

## Instalação

### Opção 1: Docker (Recomendado)

#### Pré-requisitos
- Docker
- Docker Compose (opcional, mas recomendado)

#### Passos com Docker Compose

1. Clone o repositório:
```bash
git clone git@github.com:TrindadeBRA/hashnote-api.git
cd hashnote-api
```

2. Configure o ambiente:
```bash
cp env.example .env
# Edite .env se necessário
```

3. Build e inicie o container:
```bash
docker-compose up --build -d
```

4. Acesse a API:
```
http://localhost:8000
```

**📖 Para mais detalhes sobre Docker, veja [DOCKER.md](DOCKER.md)**

#### Passos com Docker apenas

1. Build da imagem:
```bash
docker build -t hashnote-api .
```

2. Execute o container:
```bash
docker run -d \
  --name hashnote-api \
  -p 8000:80 \
  -v $(pwd)/.env):/var/www/html/.env:ro \
  -v $(pwd)/data:/var/www/html/data \
  hashnote-api
```

#### Comandos úteis

- Ver logs: `docker-compose logs -f` ou `docker logs -f hashnote-api`
- Parar: `docker-compose down` ou `docker stop hashnote-api`
- Reiniciar: `docker-compose restart` ou `docker restart hashnote-api`
- Executar comandos no container: `docker-compose exec app sh` ou `docker exec -it hashnote-api sh`

### Opção 2: Instalação Local

#### Pré-requisitos

- PHP 8.2 ou superior
- Composer
- Extensões PHP: `pdo`, `pdo_sqlite`, `curl`, `json`

#### Passos

1. Clone o repositório:
```bash
git clone git@github.com:TrindadeBRA/hashnote-api.git
cd hashnote-api
```

2. Instale as dependências:
```bash
composer install
```

3. Configure o ambiente:
```bash
cp env.example .env
# Edite .env conforme necessário
```

4. Configure o banco de dados:
```bash
composer run-script setup
# ou
php scripts/setup.php
```

5. Inicie o servidor de desenvolvimento:
```bash
php -S localhost:8000 -t public
```

A API estará disponível em `http://localhost:8000`

---

**Nota**: Se estiver usando Docker, a API já estará rodando após o `docker-compose up`. Pule os passos 2-5 acima.

## Configuração

### Variáveis de Ambiente (.env)

```env
APP_NAME=HashNote API
APP_VERSION=1.0.0
APP_ENV=development

# Database
DB_PATH=data/app.sqlite

# Blockchain
BLOCKCHAIN_MODE=mock              # mock | rpc_only | server_sign
BLOCKCHAIN_RPC_URL=http://localhost:8545
BLOCKCHAIN_CONTRACT_ADDRESS=
BLOCKCHAIN_PRIVATE_KEY=
BLOCKCHAIN_NETWORK=localhost

# Security
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600
JOB_TOKEN=change-me-in-production

# Logging
LOG_LEVEL=INFO
```

### Modos Blockchain

#### 1. `mock` (Padrão - Recomendado para POC)
- Simula transações blockchain
- Gera `tx_hash` fake
- Confirma automaticamente após 5-10 segundos (via `/v1/jobs/tick`)
- **Funciona sem configuração adicional**

#### 2. `rpc_only`
- Apenas leitura (verificação de transações)
- Não suporta escrita (retorna 501)
- Requer `BLOCKCHAIN_RPC_URL` configurado
- Útil para verificar transações já existentes

#### 3. `server_sign` (V2 - Não implementado)
- Servidor assina e paga gas
- Requer `BLOCKCHAIN_PRIVATE_KEY` e `BLOCKCHAIN_CONTRACT_ADDRESS`
- **Não implementado nesta POC** - retorna 501 com instruções

## Endpoints

### Documentação

- **GET `/docs`** - Swagger UI
- **GET `/openapi.yaml`** - Especificação OpenAPI 3.0

### API

- **GET `/health`** - Health check
- **POST `/v1/messages`** - Criar mensagem
- **GET `/v1/messages/{id}`** - Obter mensagem
- **GET `/v1/messages/{id}/verify`** - Verificar na blockchain
- **POST `/v1/jobs/tick`** - Processar jobs pendentes (requer `X-Job-Token`)

## Exemplos de Uso

### Criar uma mensagem

```bash
curl -X POST http://localhost:8000/v1/messages \
  -H "Content-Type: application/json" \
  -d '{"message": "Hello, blockchain!"}'
```

Resposta:
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "message": "Hello, blockchain!",
  "msg_hash": "0x1234567890abcdef...",
  "tx_hash": "0xabcdef1234567890...",
  "status": "pending",
  "block_number": null,
  "confirmed_at": null,
  "created_at": "2024-01-15T10:25:00Z"
}
```

### Obter mensagem

```bash
curl http://localhost:8000/v1/messages/{id}
```

### Verificar na blockchain

```bash
curl http://localhost:8000/v1/messages/{id}/verify
```

### Processar jobs (modo mock)

```bash
curl -X POST http://localhost:8000/v1/jobs/tick \
  -H "X-Job-Token: change-me-in-production"
```

## Estrutura do Projeto

```
hashnote-api/
├── config/
│   ├── dependencies.php    # Configuração DI
│   ├── routes.php          # Definição de rotas
│   └── middleware.php      # Middlewares (rate limit, etc)
├── data/
│   └── app.sqlite          # Banco SQLite (gerado)
├── public/
│   └── index.php           # Entry point
├── scripts/
│   └── setup.php           # Setup do banco
├── src/
│   ├── App/
│   ├── Controller/         # Controllers
│   ├── Domain/             # Entidades e interfaces
│   ├── Infrastructure/     # Implementações (Blockchain, Persistence, etc)
│   └── Service/            # Lógica de negócio
├── swagger/
│   └── openapi.yaml        # Especificação OpenAPI
└── composer.json
```

## Segurança

### Rate Limiting
- Limite por IP (em memória)
- Configurável via `RATE_LIMIT_REQUESTS` e `RATE_LIMIT_WINDOW`
- Headers de resposta: `X-RateLimit-Remaining`, `Retry-After`

### Validação de Input
- Mensagens: 1-280 caracteres (trim)
- UUIDs validados
- Headers de segurança básicos

## Modo Real (V2 - Futuro)

Para usar modo real com assinatura de servidor:

1. Configure `BLOCKCHAIN_MODE=server_sign`
2. Configure `BLOCKCHAIN_PRIVATE_KEY` (chave privada sem `0x`)
3. Configure `BLOCKCHAIN_CONTRACT_ADDRESS` (endereço do contrato)
4. Configure `BLOCKCHAIN_RPC_URL` (endpoint JSON-RPC)

**⚠️ ATENÇÃO**: Esta funcionalidade não está implementada nesta POC. O endpoint retornará 501.

## Desenvolvimento

### Executar testes (quando implementados)
```bash
composer test
```

### Logs
Os logs são enviados para `stderr` via Monolog.

## Documentação Adicional

- **[Estado Atual e Migração do Mock](docs/ESTADO_ATUAL_E_MIGRACAO.md)** - Documentação completa sobre o estado atual do projeto, regras de negócio, e caminho para migração do mock para blockchain real.

## Licença

MIT

