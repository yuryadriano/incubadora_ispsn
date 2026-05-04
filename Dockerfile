# Dockerfile
# Imagem base: PHP 8.2 com Apache
FROM php:8.2-apache

# Variáveis de ambiente
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data

# Instalar extensões PHP necessárias
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libzip-dev \
       libpng-dev \
       libjpeg-dev \
       libfreetype6-dev \
       libicu-dev \
       curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo_mysql zip intl \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite para URLs amigáveis
RUN a2enmod rewrite

# Copiar configuração PHP (previne 504 Gateway Timeout)
COPY php.ini /usr/local/etc/php/conf.d/incubadora.ini

# Copiar e preparar o entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Healthcheck — Docker sabe se o serviço está vivo
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Porta padrão do Apache
EXPOSE 80

# O código fonte vem via volume (docker-compose.yml)
# Não é necessário COPY do código aqui — basta git pull no servidor
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
