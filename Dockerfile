FROM php:8.4-fpm

# Instalamos las extensiones de PHP necesarias
# GD con JPEG y WEBP: sin el configure, la imagen oficial compila GD solo con
# PNG, y las fotos del equipo (JPEG) no se podian reducir ni hacer miniatura.
RUN apt-get update && apt-get install -y libpq-dev libzip-dev libgd-dev \
        libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo pdo_mysql bcmath zip gd

# Instalamos Node.js (en la versión 14.x, puedes cambiar la versión si lo prefieres)
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Instalamos Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configuramos PHP (sube los tamaños permitidos para la carga de archivos)
RUN echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/upload-max-filesize.ini
RUN echo "post_max_size = 100M" > /usr/local/etc/php/conf.d/post-size-max.ini

# Establecemos el directorio de trabajo en /var/www
WORKDIR /var/www/html

# Exponemos el puerto 80 para acceder a la aplicación desde el host
EXPOSE 9000

# Iniciamos PHP-FPM
CMD ["php-fpm"]
