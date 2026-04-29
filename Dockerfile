# 1. Fase de Base: Usar a imagem oficial do PHP com Apache
FROM php:8.2-apache

# 2. Configuração de Variáveis de Ambiente
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data

# 3. Instalar Extensões PHP Necessárias
# - mysqli: Necessário para a conexão com o banco de dados.
# - pdo_mysql: Alternativa moderna de conexão.
# - gd: Para processamento de imagens (blog/galeria).
# - intl, zip: Extensões comuns em projetos PHP.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libzip-dev \
       libpng-dev \
       libjpeg-dev \
       libfreetype6-dev \
       libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
# 4. Configurar o Servidor Web (Apache)
# Habilitar o mod_rewrite para URLs amigáveis
RUN a2enmod rewrite

# Truque de compatibilidade para caminhos fixos /incubadora_ispsn/
RUN ln -s /var/www/html /var/www/html/incubadora_ispsn

# Criar um index.php na raiz que redireciona para o website público
RUN echo '<?php header("Location: /incubadora_ispsn/public/website/"); exit;' > /var/www/html/index.php

# 5. Copiar o Código Fonte
COPY . /var/www/html/

# 6. Definir Permissões (Crucial para uploads)
# Garantir que as pastas de imagens tenham permissões de escrita para o Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/assets/img/blog \
    && mkdir -p /var/www/html/assets/img/galeria \
    && chmod -R 775 /var/www/html/assets/img/blog \
    && chmod -R 775 /var/www/html/assets/img/galeria

# 7. Configuração Adicional do PHP (Opcional)
# Se necessário, você pode adicionar um php.ini personalizado
# COPY php.ini /usr/local/etc/php/conf.d/

# O Apache expõe a porta 80 por padrão
EXPOSE 80
