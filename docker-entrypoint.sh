#!/bin/bash
# docker-entrypoint.sh
# Executado no arranque do container — cria symlink e ajusta permissões

set -e

# Criar o symlink de compatibilidade (após o volume ser montado)
# /var/www/html/incubadora_ispsn → /var/www/html
if [ ! -L /var/www/html/incubadora_ispsn ]; then
    ln -sf /var/www/html /var/www/html/incubadora_ispsn
    echo "[entrypoint] Symlink criado: /var/www/html/incubadora_ispsn -> /var/www/html"
fi

# Criar pastas de upload se não existirem
mkdir -p /var/www/html/assets/img/blog
mkdir -p /var/www/html/assets/img/galeria
mkdir -p /var/www/html/uploads

# Ajustar permissões nas pastas de escrita
chown -R www-data:www-data /var/www/html/assets/img /var/www/html/uploads 2>/dev/null || true
chmod -R 775 /var/www/html/assets/img /var/www/html/uploads 2>/dev/null || true

echo "[entrypoint] Container pronto. A iniciar Apache..."

# Iniciar Apache em foreground
exec apache2-foreground
