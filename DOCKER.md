# Guia de Uso com Docker

Este guia explica como usar o HashNote API com Docker.

## Pré-requisitos

- Docker instalado
- Docker Compose (opcional, mas recomendado)

## Método 1: Docker Compose (Recomendado)

### 1. Build e Iniciar

```bash
# Build da imagem e iniciar container
docker-compose up --build -d

# Ou apenas iniciar se já foi buildado
docker-compose up -d
```

### 2. Verificar Status

```bash
# Ver logs em tempo real
docker-compose logs -f

# Ver status dos containers
docker-compose ps
```

### 3. Acessar a API

A API estará disponível em:
- **API**: http://localhost:8000
- **Documentação Swagger**: http://localhost:8000/docs
- **Health Check**: http://localhost:8000/health

### 4. Comandos Úteis

```bash
# Parar container
docker-compose down

# Parar e remover volumes (apaga banco de dados)
docker-compose down -v

# Reiniciar container
docker-compose restart

# Rebuild após mudanças no código
docker-compose up --build -d

# Executar comandos dentro do container
docker-compose exec app sh
docker-compose exec app php scripts/setup.php
```

## Método 2: Docker Puro

### 1. Build da Imagem

```bash
docker build -t hashnote-api .
```

### 2. Executar Container

```bash
docker run -d \
  --name hashnote-api \
  -p 8000:8000 \
  -v $(pwd)/.env:/var/www/html/.env:ro \
  -v $(pwd)/data:/var/www/html/data \
  hashnote-api
```

### 3. Comandos Úteis

```bash
# Ver logs
docker logs -f hashnote-api

# Parar container
docker stop hashnote-api

# Iniciar container parado
docker start hashnote-api

# Remover container
docker rm hashnote-api

# Executar comandos no container
docker exec -it hashnote-api sh
docker exec -it hashnote-api php scripts/setup.php
```

## Configuração

### Variáveis de Ambiente

1. Copie o arquivo de exemplo:
```bash
cp env.example .env
```

2. Edite o `.env` conforme necessário:
```env
BLOCKCHAIN_MODE=mock
DB_PATH=/var/www/html/data/app.sqlite
# ... outras variáveis
```

### Persistência de Dados

O banco de dados SQLite é persistido no diretório `./data` do host através de volume Docker. Isso significa que os dados são mantidos mesmo após parar/remover o container.

## Troubleshooting

### Container não inicia

```bash
# Ver logs detalhados
docker-compose logs app

# Verificar se a porta está em uso
netstat -tulpn | grep 8000
# ou
lsof -i :8000
```

### Erro de permissões no banco

```bash
# Ajustar permissões do diretório data
chmod -R 777 data/

# Ou executar dentro do container
docker-compose exec app chmod -R 777 /var/www/html/data
```

### Rebuild completo

```bash
# Remover tudo e rebuildar
docker-compose down -v
docker-compose build --no-cache
docker-compose up -d
```

### Acessar banco de dados

```bash
# Entrar no container
docker-compose exec app sh

# Dentro do container, usar sqlite3
sqlite3 /var/www/html/data/app.sqlite
.tables
SELECT * FROM messages;
```

## Desenvolvimento

### Modo Desenvolvimento

Para desenvolvimento com hot-reload, você pode montar o código como volume:

```yaml
# Adicione ao docker-compose.yml
volumes:
  - .:/var/www/html
```

E instalar dependências com dev:
```bash
docker-compose exec app composer install
```

### Testar Endpoints

```bash
# Health check
curl http://localhost:8000/health

# Criar mensagem
curl -X POST http://localhost:8000/v1/messages \
  -H "Content-Type: application/json" \
  -d '{"message": "Hello, blockchain!"}'

# Ver documentação
open http://localhost:8000/docs
```

## Produção

Para produção, considere:

1. Usar variáveis de ambiente seguras (não commit .env)
2. Configurar HTTPS (reverse proxy como Traefik/Nginx)
3. Usar banco de dados externo (PostgreSQL/MySQL) ao invés de SQLite
4. Configurar backups do volume de dados
5. Usar secrets do Docker ao invés de arquivo .env

