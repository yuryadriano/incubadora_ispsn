# 1. Fase de Base: Usar a imagem oficial do PHP com Apache
FROM php:8.2-apache

# 2. Configuração de Variáveis de Ambiente
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data

# 3. Instalar Extensões PHP Necessárias
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libzip-dev \
       libpng-dev \
       libjpeg-dev \
       libfreetype6-dev \
       libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo_mysql zip intl

# 4. Configurar o Servidor Web (Apache)
RUN a2enmod rewrite

# 5. Copiar o Código Fonte
COPY . /var/www/html/

# 6. Definir Permissões (Crucial para uploads)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

# O Apache expõe a porta 80 por padrão
EXPOSE 80
