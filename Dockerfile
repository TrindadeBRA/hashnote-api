FROM php:8.2-cli-alpine

# Instala dependências de build e runtime
RUN apk add --no-cache \
    curl \
    wget \
    git \
    unzip \
    sqlite \
    bash \
    $PHPIZE_DEPS \
    sqlite-dev

# Instala extensões PHP necessárias
RUN docker-php-ext-install pdo pdo_sqlite

# Remove dependências de build após instalação (mantém sqlite runtime)
RUN apk del $PHPIZE_DEPS sqlite-dev

# Instala Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Define diretório de trabalho
WORKDIR /var/www/html

# Copia arquivos do projeto
COPY . .

# Instala dependências do Composer
RUN composer install --no-dev --optimize-autoloader

# Cria diretório para dados
RUN mkdir -p /var/www/html/data && \
    chmod -R 777 /var/www/html/data

# Script de inicialização
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expõe porta 8000
EXPOSE 8000

# Entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/router.php"]

