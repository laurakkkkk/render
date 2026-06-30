FROM php:8.2-apache

# Habilitar módulos necesarios
RUN docker-php-ext-install mysqli

# Copiar archivos al servidor
COPY . /var/www/html/

# Permisos para la carpeta data
RUN mkdir -p /var/www/html/admin/data && chmod 755 /var/www/html/admin/data

EXPOSE 80
