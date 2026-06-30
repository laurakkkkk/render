FROM php:8.2-apache

# Copiar TODOS los archivos al servidor
COPY . /var/www/html/

# Crear carpeta data
RUN mkdir -p /var/www/html/admin/data && chmod 755 /var/www/html/admin/data

# ⭐ FORZAR que index.html sea el primero en la lista
RUN echo "DirectoryIndex index.html index.php" > /etc/apache2/conf-available/directory-index.conf && \
    a2enconf directory-index

# Habilitar módulos
RUN a2enmod rewrite

EXPOSE 80
