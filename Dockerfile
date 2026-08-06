# 1. Base Image: PHP 8.2 com Apache (idêntico à clinica-ispsn)
FROM php:8.2-apache

# 2. Variáveis de Ambiente
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

# 4. Habilitar mod_rewrite
RUN a2enmod rewrite

# 5. Copiar Código Fonte limpo (sem loops de symlink)
COPY . /var/www/html/

# 6. Truque de compatibilidade para caminhos /incubadora_ispsn/ pós-COPY
RUN ln -s /var/www/html /var/www/html/incubadora_ispsn

# 7. Permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
