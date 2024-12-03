# Usa una imagen base de PHP 7.3 con FPM y Composer
FROM php:7.3-fpm

# Instala dependencias del sistema
RUN apt-get update && apt-get install -y \
    software-properties-common \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install gd pdo pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala Composer desde una imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Establece el directorio de trabajo
WORKDIR /var/www

# Copia los archivos del proyecto Laravel al contenedor
COPY . .

# Copia el archivo .env real al contenedor
COPY .env .env

# Instala las dependencias del proyecto usando Composer
RUN composer install --prefer-dist --no-interaction --no-scripts --optimize-autoloader

# Genera la clave de la aplicación
RUN php artisan key:generate

# Da permisos de escritura a la carpeta de almacenamiento
RUN chown -R www-data:www-data /var/www/storage

# Expone el puerto 8000
EXPOSE 8000

# Comando para iniciar el servidor de Laravel
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
