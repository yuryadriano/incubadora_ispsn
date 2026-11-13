# 1. Fase de Base: Usar a imagem oficial do PHP com Apache
FROM php:8.2.35-apache

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
    && docker-php-ext-install -j$(nproc) gd mysqli pdo_mysql zip intl opcache

# 4. Configurar o Servidor Web (Apache)
# Habilitar o mod_rewrite e headers
RUN a2enmod rewrite headers

# Compatibilidade para caminhos fixos /incubadora_ispsn/
RUN ln -s /var/www/html /var/www/html/incubadora_ispsn

# Criar um index.php na raiz que redireciona para o website público
RUN echo '<?php header("Location: /incubadora_ispsn/public/website/"); exit;' > /var/www/html/index.php

# 5. Copiar o Código Fonte
COPY . /var/www/html/

# 6. Definir Permissões (Crucial para uploads)
# Garantir que as pastas de imagens e uploads existam e tenham permissões de escrita para o Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/uploads/pitches \
    && mkdir -p /var/www/html/uploads/projetos \
    && mkdir -p /var/www/html/uploads/evidencias \
    && mkdir -p /var/www/html/uploads/metas \
    && mkdir -p /var/www/html/uploads/relatorios \
    && mkdir -p /var/www/html/uploads/termos \
    && mkdir -p /var/www/html/assets/img/blog \
    && mkdir -p /var/www/html/assets/img/galeria \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/assets/img \
    && chmod -R 775 /var/www/html/uploads \
    && chmod -R 775 /var/www/html/assets/img/blog \
    && chmod -R 775 /var/www/html/assets/img/galeria

# Bloquear execução de PHP e scripts nas pastas de upload ao nível do Apache
RUN printf '%s\n' \
    '<DirectoryMatch "^/var/www/html/(incubadora_ispsn/)?(uploads|assets/img/(blog|galeria))">' \
    '    Options -ExecCGI -Indexes' \
    '    AllowOverride None' \
    '    php_admin_flag engine off' \
    '    <FilesMatch "\.(?i:php[0-9]?|phtml|phar|inc|pl|py|cgi|sh)$">' \
    '        SetHandler none' \
    '        Require all denied' \
    '    </FilesMatch>' \
    '</DirectoryMatch>' \
    > /etc/apache2/conf-available/no-php-uploads.conf \
    && a2enconf no-php-uploads

# 7. Tuning do Apache MPM Prefork e Timeouts (Prevenção definitiva de 504 Gateway Timeout)
# Em proxy reverso (Traefik/Cloudflare), KeepAlive Off liberta o worker imediatamente após cada resposta.
RUN printf '%s\n' \
    'ServerTokens Prod' \
    'ServerSignature Off' \
    'TimeOut 25' \
    'KeepAlive Off' \
    'GracefulShutdownTimeout 5' \
    '<IfModule mpm_prefork_module>' \
    '    StartServers             4' \
    '    MinSpareServers          3' \
    '    MaxSpareServers          8' \
    '    MaxRequestWorkers       50' \
    '    MaxConnectionsPerChild 1000' \
    '</IfModule>' \
    > /etc/apache2/conf-available/mpm-tuning.conf \
    && a2enconf mpm-tuning

# Healthcheck para Zero-Downtime Deployment no Coolify/Traefik
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1/health.php') === 'ok' ? 0 : 1);"

# 8. Configuração de Produção do PHP + OPcache + Limpeza de Sessões
RUN printf '%s\n' \
    'max_execution_time = 15' \
    'max_input_time = 15' \
    'default_socket_timeout = 3' \
    'mysqlnd.net_cmd_connect_timeout = 2' \
    'mysqlnd.net_read_timeout = 5' \
    'memory_limit = 128M' \
    'post_max_size = 20M' \
    'upload_max_filesize = 16M' \
    'expose_php = Off' \
    'session.gc_probability = 1' \
    'session.gc_divisor = 100' \
    'session.gc_maxlifetime = 7200' \
    'opcache.enable = 1' \
    'opcache.memory_consumption = 64' \
    'opcache.interned_strings_buffer = 8' \
    'opcache.max_accelerated_files = 4000' \
    'opcache.revalidate_freq = 60' \
    'opcache.fast_shutdown = 1' \
    > /usr/local/etc/php/conf.d/zz-production.ini

# O Apache expõe a porta 80 por padrão
EXPOSE 80
