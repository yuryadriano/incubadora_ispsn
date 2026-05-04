# Dockerfile
FROM php:8.2-apache

ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data

# Instalar extensões PHP necessárias
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo_mysql zip intl \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Copiar configuração PHP (limita tempo de execução, previne 504)
COPY php.ini /usr/local/etc/php/conf.d/incubadora.ini

# Symlink de compatibilidade para paths /incubadora_ispsn/
RUN ln -s /var/www/html /var/www/html/incubadora_ispsn

# Copiar todo o código fonte
COPY . /var/www/html/

# Permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/assets/img/blog \
    && mkdir -p /var/www/html/assets/img/galeria \
    && chmod -R 775 /var/www/html/assets/img/blog \
    && chmod -R 775 /var/www/html/assets/img/galeria

EXPOSE 80
